// Components
import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accountForm } from '@/lib/account-form';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Forgot password" />

            {status && (
                <div role="status" className={`mb-6 ${accountForm.status}`}>
                    {status}
                </div>
            )}

            <Form {...email.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
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
                                autoComplete="off"
                                autoFocus
                                placeholder="email@example.com"
                                className={accountForm.field}
                            />
                            <InputError
                                message={errors.email}
                                className={accountForm.error}
                            />
                        </div>

                        <button
                            type="submit"
                            className={accountForm.submit}
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            Email password reset link
                        </button>

                        <div className={accountForm.footer}>
                            Or, return to{' '}
                            <TextLink href={login()} className="font-semibold">
                                log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Forgot password',
    description: 'Enter your email to receive a password reset link',
};
