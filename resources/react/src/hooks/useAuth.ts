import {useAuthStore} from "@/stores/authStore";

export function useAuth() {
    const user = useAuthStore((s) => s.user);
    const checked = useAuthStore((s) => s.checked);
    const login = useAuthStore((s) => s.login);
    const logout = useAuthStore((s) => s.logout);

    return {
        user,
        checked,
        isAuthenticated: !!user,
        login,
        logout,
    }

}
