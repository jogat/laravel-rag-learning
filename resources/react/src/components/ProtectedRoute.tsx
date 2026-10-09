import type { ReactNode } from 'react'
import {Navigate} from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';

export function ProtectedRoute  ({children}: {children: ReactNode}) {
    const { checked, isAuthenticated } = useAuth();


    if(!checked){
        return <div className="p-8 text-gray-500">Loading...</div>;
    }

    if (!isAuthenticated){
        return <Navigate to="/login" replace/>;
    }

    return children;
}
