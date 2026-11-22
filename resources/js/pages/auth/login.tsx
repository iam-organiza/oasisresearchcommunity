import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="email"
                                    className="font-semibold"
                                >
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    className="h-12 rounded-control border-brand-line bg-brand-sheet px-4 text-base shadow-none focus-visible:border-brand-navy focus-visible:ring-brand-navy/20"
                                />
                                <InputError
                                    message={errors.email}
                                    className="font-semibold text-brand-crimson-strong"
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label
                                        htmlFor="password"
                                        className="font-semibold"
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
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                    className="h-12 rounded-control border-brand-line bg-brand-sheet px-4 text-base shadow-none focus-visible:border-brand-navy focus-visible:ring-brand-navy/20"
                                />
                                <InputError
                                    message={errors.password}
                                    className="font-semibold text-brand-crimson-strong"
                                />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className="size-5 rounded-[5px] border-brand-line bg-brand-sheet data-[state=checked]:border-brand-navy data-[state=checked]:bg-brand-navy"
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <button
                                type="submit"
                                className="mt-2 btn min-h-14 w-full btn-ink text-body01 disabled:opacity-60"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </button>
                        </div>

                        <div className="border-t border-brand-line pt-6 text-brand-navy/70">
                            Don't have an account?{' '}
                            <TextLink
                                href={register()}
                                tabIndex={5}
                                className="font-semibold"
                            >
                                Sign up
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mt-6 rounded-control bg-brand-sheet px-4 py-3 font-semibold text-green-700 ring-1 ring-brand-line">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
