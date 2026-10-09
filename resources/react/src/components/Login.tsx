import { useState, type SubmitEvent } from 'react';
import {Navigate} from "react-router-dom";
import {useAuth} from "@/hooks/useAuth";


export default function Login() {
    const { checked, isAuthenticated, login } = useAuth();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [submitting, setSubmitting] = useState<boolean>(false);
    const [error, setError] =  useState<string | null>(null);

    async function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        setSubmitting(true);
        setError(null);

        try {
            await login(email, password);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
        } finally {
            setSubmitting(false);
        }
    }

    if (!checked) {
        return <div className="p-8 text-gray-500">Loading...</div>;
    }

    if (isAuthenticated) {
        return <Navigate to="/chat" replace />;
    }

    return (
        <div className="flex h-screen items-center justify-center">
            <form onSubmit={handleSubmit} className="w-full max-w-sm space-y-4 rounded bg-white p-6 shadow">
                <h1 className="text-xl font-semibold">Sign in</h1>
                <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Email"
                    required
                    className="w-full rounded border px-3 py-2"
                />
                <input
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Password"
                    required
                    className="w-full rounded border px-3 py-2"
                />
                {error && <div className="text-sm text-red-600">{error}</div>}
                <button
                    disabled={submitting}
                    className="w-full rounded bg-blue-600 py-2 text-white disabled:opacity-50"
                >
                    {submitting ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
        </div>
    )
}
