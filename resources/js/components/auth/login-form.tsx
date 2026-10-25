import { Form } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    canResetPassword: boolean;
    autoFocus?: boolean;
    options?: ComponentProps<typeof Form>['options'];
    footer?: ReactNode;
};

export default function LoginForm({
    canResetPassword,
    autoFocus = false,
    options,
    footer,
}: Props) {
    return (
        <Form
            {...store.form()}
            options={options}
            resetOnSuccess={['password']}
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="login-identifier">
                                Mobile number or Student ID
                            </Label>
                            <Input
                                id="login-identifier"
                                type="text"
                                name="login"
                                required
                                autoFocus={autoFocus}
                                autoComplete="username"
                                placeholder="01XXXXXXXXX or student ID"
                            />
                            <p className="text-xs text-muted-foreground">
                                Admins and teachers use their mobile number;
                                students use their student ID.
                            </p>
                            <InputError message={errors.login} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="login-password">Password</Label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="ml-auto text-sm"
                                    >
                                        Forgot your password?
                                    </TextLink>
                                )}
                            </div>
                            <PasswordInput
                                id="login-password"
                                name="password"
                                required
                                autoComplete="current-password"
                                placeholder="Password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox id="login-remember" name="remember" />
                            <Label htmlFor="login-remember">Remember me</Label>
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 w-full"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Log in
                        </Button>
                    </div>

                    {footer}
                </>
            )}
        </Form>
    );
}
