import { useEffect } from 'react';
import Chat from '@/components/Chat';
import Login from '@/components/Login';
import { useAuthStore } from '@/stores/authStore';

export default function App() {
    const user = useAuthStore((s) => s.user);
    const checked = useAuthStore((s) => s.checked);
    const fetchUser = useAuthStore((s) => s.fetchUser);

    useEffect(() => {
        fetchUser();
    }, [fetchUser]);

    if (!checked) return <div className="p-8 text-gray-500">Loading…</div>;

    return user ? <Chat /> : <Login />;
}
