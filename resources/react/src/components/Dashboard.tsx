import {useAuth} from "@/hooks/useAuth";


export default function Dashboard() {
    const { user, logout } = useAuth();

    return (
        <div className="p-4">
            <h1 className="text-lg font-semibold">Dashboard</h1>
            <p>Welcome {user?.name}</p>
            <button onClick={logout} className="mt-4 bg-red-500 text-white p-2 rounded">
                Logout
            </button>
        </div>
    )
}
