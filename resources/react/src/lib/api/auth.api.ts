import {request} from '../api'
export type User = { id: number; name: string; email: string };
export type Project = { slug: string; name: string; language: string };

export const getUser = () => request<User>('/api/user');

export const login = (email: string, password: string) =>
    request<User>('/api/login', { method: 'POST', body: JSON.stringify({ email, password }) });

export const logout = () => request<void>('/api/logout', { method: 'POST' });




