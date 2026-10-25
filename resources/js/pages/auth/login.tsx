import { Head } from '@inertiajs/react';
import LoginForm from '@/components/auth/login-form';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            <LoginForm
                canResetPassword={canResetPassword}
                autoFocus
                footer={
                    <p className="text-center text-sm text-muted-foreground">
                        Accounts are provided by the university. Contact the
                        administrator if you need access.
                    </p>
                }
            />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your mobile number or student ID and password',
};
