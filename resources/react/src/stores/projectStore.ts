import { create } from 'zustand';
import { getProjects, type Project } from '@/lib/api/project.api';

type ProjectState = {
    projects: Project[];
    current: string | null; // slug del proyecto seleccionado
    loading: boolean;
    error: string | null;
    fetchProjects: () => Promise<void>;
    select: (slug: string) => void;
    reset: () => void;
};

export const useProjectStore = create<ProjectState>()((set, get) => ({
    projects: [],
    current: null,
    loading: false,
    error: null,

    fetchProjects: async() => {
        if (get().loading) return;
        set({loading: true, error: null});
        try {
            const projects = await getProjects();
            set({projects: projects, current: get().current ?? projects[0]?.slug ?? null});
        } catch (error) {
            set({error: error instanceof Error ? error.message : 'Unknown error'});
        } finally {
            set({loading: false});
        }
    },
    select: (slug: string) => set({current: slug}),
    reset: () => set({projects: [], current: null, error: null}),
}));
