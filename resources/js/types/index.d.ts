export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    email_verified_at?: string;
}

export interface AuthAbilities {
    tasks_view?: boolean;
    projects_view?: boolean;
    external_parties_view?: boolean;
    users_view?: boolean;
    departments_view?: boolean;
    roles_view?: boolean;
    cartable_view?: boolean;
    notification_view?: boolean;
    settings_manage?: boolean;
    users_view?: boolean;
    departments_view?: boolean;
    roles_view?: boolean;
    departments_create?: boolean;
    departments_edit?: boolean;
    departments_delete?: boolean;
    departments_toggle_status?: boolean;
    departments_manage_positions?: boolean;
    users_manage_departments?: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        abilities?: AuthAbilities;
    };
};
