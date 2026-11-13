export type User = {
    id: number;
    name: string;
    email: string;
    /** `entry` is the lab tablet: daybook and dentist notebooks only. */
    role: 'admin' | 'entry';
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
