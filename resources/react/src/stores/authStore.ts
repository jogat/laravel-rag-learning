import { create } from 'zustand';
import { useProjectStore } from './projectStore';
import {type User, getUser, login, logout} from "@/lib/api/auth.api";

type AuthState = {
    user: User | null;
    checked: boolean; // ¿ya le preguntamos al servidor si hay sesión?
    fetchUser: () => Promise<void>;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
};

export const useAuthStore = create<AuthState>()((set) => ({
    user: null,
    checked: false,
    fetchUser: async () => {
        try{
            set({user: await getUser()});
        } catch(error) {
            set({ user: null});
        } finally {
            set({checked: true});
        }
    },
    login: async (email: string, password: string) => {
        set({user: await login(email, password)});
    },
    logout: async () => {
        await logout();
        useProjectStore.getState().reset();
        set({user: null});
    }
}))
