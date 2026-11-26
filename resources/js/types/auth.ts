export type User = {
    id: number;
    user_ref: string;
    first_name: string;
    last_name: string;
    name: string;
    role: 'admin' | 'editor' | 'registrar' | 'member';
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /** Which staff areas this person may open. */
    can: {
        manageContent: boolean;
        manageMembers: boolean;
        manageStaff: boolean;
    };
};
