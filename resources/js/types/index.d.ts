export interface User {
    id: number;
    name: string;
    last_name?: string | null;
    email: string;
    email_verified_at?: string | null;
    avatar_url?: string | null;
    roles?: string[];
    permissions?: string[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};
