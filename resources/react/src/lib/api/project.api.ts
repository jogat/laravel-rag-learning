
import {request} from '../api'

export type Project = { slug: string; name: string; language: string };

export const getProjects = () => request<Project[]>('/api/projects');

