import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Notice } from '@/components/notice';
import { PasswordChecklist } from '@/components/password-checklist';
import type { PasswordRequirement } from '@/components/password-checklist';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accountForm } from '@/lib/account-form';
import { request, update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    status?: string | null;
    passwordRules: string;
    passwordChecklist: PasswordRequirement[];
};

export default function ResetPassword({
    token,
    email,
    status,
    passwordRules,
    passwordChecklist,
}: Props) {
    const [password, setPassword] = useState('');
    const newLink = request({ query: email ? { email } : {} });

    return (
        <>
            <Head title="Choose a new password" />

            {status && (
                <Notice tone="info" className="mb-6">
                    {status}
                </Notice>
            )}

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                noValidate
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="email"
                                className={accountForm.label}
                            >
                                Account
                            </Label>
                            {/* Fixed by the reset link, so it is shown on the pale tone and cannot be edited.
                                autoComplete="username" lets a password manager save the new password against it. */}
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="username"
                                value={email}
                                className={`${accountForm.field} bg-brand-paper text-brand-navy/70`}
                                readOnly
                            />
                            <InputError
                                message={errors.email}
                                className={accountForm.error}
                            />
                            {/* Expired while the page was open: offer a new one. */}
                            {errors.email && (
                                <p className="text-sm">
                                    <TextLink
                                        href={newLink}
                                        className="font-semibold"
                                    >
                                        Send me a new link
                                    </TextLink>
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label
                                htmlFor="password"
                                className={accountForm.label}
                            >
                                New password
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                className={accountForm.field}
                                autoFocus
                                passwordrules={passwordRules}
                                aria-describedby="password-rules"
                                onChange={(event) =>
                                    setPassword(event.target.value)
                                }
                            />
                            <InputError
                                message={errors.password}
                                className={accountForm.error}
                            />
                            <PasswordChecklist
                                id="password-rules"
                                items={passwordChecklist}
                                value={password}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label
                                htmlFor="password_confirmation"
                                className={accountForm.label}
                            >
                                Type it again
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                className={accountForm.field}
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                                className={accountForm.error}
                            />
                        </div>

                        <button
                            type="submit"
                            className={accountForm.submit}
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            Save new password
                        </button>
                    </div>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Choose a new password',
    description:
        'Saving it logs your account out on any other devices, and you log in again with the new one.',
};
