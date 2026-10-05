import {useEffect, useState, type SubmitEvent} from "react";
import {sendMessage, type ChatDebug} from '@/lib/api/chat.api';
import { useAuthStore } from '@/stores/authStore';
import { useProjectStore } from '@/stores/projectStore';

type Message = {role: 'user' | 'assistant'; text: string; debug?: ChatDebug};

export default function Chat() {
    const projects = useProjectStore((s) => s.projects);
    const project = useProjectStore((s) => s.current);
    const fetchProjects = useProjectStore((s) => s.fetchProjects);
    const selectProject = useProjectStore((s) => s.select);
    const user = useAuthStore((s) => s.user);
    const logout = useAuthStore((s) => s.logout);

    useEffect(() => {
        fetchProjects();
    }, [fetchProjects]);


    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        const text = input.trim();
        if (!text || loading || !project) return;

        setMessages((prevState) => [...prevState, {role: 'user', text}]);
        setInput('');
        setLoading(true);
        setError(null);

        try {
            const { reply, debug } = await sendMessage(project, text);
            setMessages((prev) => [...prev, {role: 'assistant', text: reply, debug}]);
        } catch (error) {
            setError(error instanceof Error ? error.message : 'Unknown error');
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="mx-auto flex h-screen max-w-2xl flex-col p-4">
            <header className="mb-4 flex items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Assistant</h1>
                <div className="flex items-center gap-3">
                    <select
                        value={project ?? ''}
                        onChange={(e) => {
                            selectProject(e.target.value);
                            setMessages([]);
                        }}
                        className="rounded border px-2 py-1"
                    >
                        {projects.map((p) => (
                            <option key={p.slug} value={p.slug}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                    <span className="text-sm text-gray-600">{user?.name}</span>
                    <button onClick={logout} className="text-sm text-blue-600 hover:underline">
                        Log out
                    </button>
                </div>
            </header>

            <div className="flex-1 space-y-3 overflow-y-auto rounded bg-white p-4 shadow">
                {messages.map((m,i) => (
                    <div
                    key={i}
                    className={`max-w-[80%] whitespace-pre-wrap rounded-lg px-3 py-2 ${
                        m.role === 'user' ? 'ml-auto bg-blue-600 text-white' : 'bg-gray-200'
                    }`}
                    >
                        {m.text}
                        {m.debug && (
                            <details className="mt-2 text-xs text-gray-600">
                                <summary className="cursor-pointer">
                                    debug · intent: {m.debug.intent}
                                    {m.debug.blocked_by_intent_classifier && ' (blocked)'}
                                    {' · tools: '}
                                    {m.debug.tool_calls.map((c) => c.tool).join(' → ') || 'none'}
                                </summary>
                                <pre className="mt-1 overflow-x-auto whitespace-pre-wrap">
                                    {JSON.stringify(m.debug, null, 2)}
                                </pre>
                            </details>
                        )}
                    </div>
                ))}
                {loading && <div className="text-sm text-gray-500">Loading...</div>}
                {error && <div className="text-sm text-red-600">{error}</div>}
            </div>

            <form onSubmit={handleSubmit} className="mt-4 flex gap-2">
                <input
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    placeholder="Ask your question…"
                    className="flex-1 rounded border px-3 py-2"
                />
                <button
                    disabled={loading}
                    className="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                >
                    Submit
                </button>
            </form>
        </div>
    )
}
