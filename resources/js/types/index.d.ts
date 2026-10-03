export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface AuthAbilities {
    tasks_view?: boolean;
    projects_view?: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        abilities?: AuthAbilities;
    };
};
