import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accountForm } from '@/lib/account-form';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Notice } from '@/components/notice';

type Props = {
    status?: string;
    email?: string;
    canResetPassword: boolean;
};

export default function Login({ status, email, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            {status && (
                <Notice
                    tone={
                        /expired|incomplete/i.test(status)
                            ? 'warning'
                            : 'success'
                    }
                    className="mb-6"
                >
                    {status}
                </Notice>
            )}
            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                resetOnError={['password']}
                noValidate
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="email"
                                    className={accountForm.label}
                                >
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus={!email}
                                    defaultValue={email}
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    className={accountForm.field}
                                />
                                <InputError
                                    message={errors.email}
                                    className={accountForm.error}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label
                                        htmlFor="password"
                                        className={accountForm.label}
                                    >
                                        Password
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-caption01 font-semibold"
                                            tabIndex={5}
                                        >
                                            Forgot your password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoFocus={Boolean(email)}
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    className={accountForm.field}
                                />
                                <InputError
                                    message={errors.password}
                                    className={accountForm.error}
                                />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className={accountForm.checkbox}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <button
                                type="submit"
                                className={accountForm.submit}
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </button>
                        </div>

                        <div className={accountForm.footer}>
                            Don't have an account?{' '}
                            <TextLink
                                href={register()}
                                tabIndex={6}
                                className="font-semibold"
                            >
                                Sign up
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
