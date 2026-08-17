export type TaskStatus = 'pending' | 'doing' | 'done';

export type TaskPriority = 'low' | 'normal' | 'high' | 'urgent';

interface Named {
    id: number;
    name: string;
}

export interface Task {
    id: number;
    title: string;
    description: string | null;
    status: TaskStatus;
    priority: TaskPriority;
    due_date: string | null;
    due_date_label: string | null;
    /** Calculado no servidor: só vale para tarefa não concluída. */
    is_overdue: boolean;
    client_id: number | null;
    project_id: number | null;
    user_id: number | null;
    client: Named | null;
    project: Named | null;
    user: Named | null;
}

export interface TaskStats {
    pending: number;
    doing: number;
    overdue: number;
    doneToday: number;
    mine: number;
}

export interface TaskFilters {
    search: string;
    status: string;
    mine: boolean;
    overdue: boolean;
    done_today: boolean;
    sort: string;
    direction: 'asc' | 'desc';
}

export interface TaskFormData {
    title: string;
    description: string;
    status: TaskStatus;
    priority: TaskPriority;
    due_date: string;
    client_id: string;
    user_id: string;

    [key: string]: string;
}

export const EMPTY_TASK_FORM: TaskFormData = {
    title: '',
    description: '',
    status: 'pending',
    priority: 'normal',
    due_date: '',
    client_id: '',
    user_id: '',
};

export function toTaskFormData(task: Task): TaskFormData {
    return {
        title: task.title,
        description: task.description ?? '',
        status: task.status,
        priority: task.priority,
        due_date: task.due_date ?? '',
        client_id: task.client_id === null ? '' : String(task.client_id),
        user_id: task.user_id === null ? '' : String(task.user_id),
    };
}
