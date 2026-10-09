import Chat from '@/components/Chat';
import Login from '@/components/Login';
import {Routes, Route, Navigate} from 'react-router-dom'
import { ProtectedRoute } from '@/components/ProtectedRoute';
import Dashboard from "@/components/Dashboard";
import {useAuthStore} from "@/stores/authStore";
import {useEffect} from "react";


export default function App() {

    const fetchUser = useAuthStore((s) => s.fetchUser);

    useEffect(() => {
        fetchUser();
    }, [fetchUser]);

    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route
                path="/chat"
                element={
                    <ProtectedRoute>
                        <Chat />
                    </ProtectedRoute>
                }
            />
            <Route
                path="/dashboard"
                element={
                    <ProtectedRoute>
                        <Dashboard />
                    </ProtectedRoute>
                }
            />

            <Route path="/" element={<Navigate to="/chat" replace />} />
        </Routes>
    );
}
