import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import LoginForm from '@/components/auth/login-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

type Props = {
    canResetPassword: boolean;
    status?: string;
};

// Stay on the landing page when validation fails.
const formOptions = { preserveScroll: true, preserveState: true };

export default function LandingAuthCard({ canResetPassword, status }: Props) {
    const { auth } = usePage().props;

    if (auth.user) {
        return (
            <Card className="shadow-xl shadow-primary/5">
                <CardHeader>
                    <CardTitle className="text-xl">
                        Welcome back, {auth.user.name}
                    </CardTitle>
                    <CardDescription>
                        You are signed in to BAUST CAMPUS.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button asChild className="w-full">
                        <Link href={dashboard()}>
                            Go to dashboard
                            <ArrowRight />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="shadow-xl shadow-primary/5">
            <CardHeader>
                <CardTitle className="text-xl">Sign in</CardTitle>
                <CardDescription>
                    Use your mobile number or student ID and password.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {status && (
                    <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700">
                        {status}
                    </div>
                )}

                <LoginForm
                    canResetPassword={canResetPassword}
                    options={formOptions}
                    footer={
                        <p className="text-center text-sm text-muted-foreground">
                            Accounts are provided by the university. Contact the
                            administrator if you need access.
                        </p>
                    }
                />
            </CardContent>
        </Card>
    );
}
