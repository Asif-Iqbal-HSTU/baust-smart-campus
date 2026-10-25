export type Role = 'admin' | 'admission_officer' | 'teacher' | 'student';

export type User = {
    id: number;
    name: string;
    role: Role;
    mobile: string | null;
    student_id: string | null;
    email: string | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
