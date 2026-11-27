import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PasswordChecklist } from '@/components/password-checklist';
import type { PasswordRequirement } from '@/components/password-checklist';
import PasswordInput from '@/components/password-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accountForm } from '@/lib/account-form';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
    passwordChecklist: PasswordRequirement[];
};

export default function ResetPassword({
    token,
    email,
    passwordRules,
    passwordChecklist,
}: Props) {
    const [password, setPassword] = useState('');

    return (
        <>
            <Head title="Reset password" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="email"
                                className={accountForm.label}
                            >
                                Email
                            </Label>
                            {/* Fixed by the reset link, so it is shown on the pale tone and cannot be edited. */}
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                className={`${accountForm.field} bg-brand-paper text-brand-navy/70`}
                                readOnly
                            />
                            <InputError
                                message={errors.email}
                                className={accountForm.error}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label
                                htmlFor="password"
                                className={accountForm.label}
                            >
                                Password
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
                                Confirm password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                className={accountForm.field}
                                placeholder="Confirm password"
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
                            Reset password
                        </button>
                    </div>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Reset password',
    description: 'Please enter your new password below',
};
