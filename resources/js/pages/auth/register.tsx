import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accountForm } from '@/lib/account-form';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="first_name"
                                        className={accountForm.label}
                                    >
                                        First name
                                    </Label>
                                    <Input
                                        id="first_name"
                                        type="text"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="given-name"
                                        name="first_name"
                                        className={accountForm.field}
                                    />
                                    <InputError
                                        message={errors.first_name}
                                        className={accountForm.error}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="last_name"
                                        className={accountForm.label}
                                    >
                                        Last name
                                    </Label>
                                    <Input
                                        id="last_name"
                                        type="text"
                                        required
                                        tabIndex={1}
                                        autoComplete="family-name"
                                        name="last_name"
                                        className={accountForm.field}
                                    />
                                    <InputError
                                        message={errors.last_name}
                                        className={accountForm.error}
                                    />
                                </div>
                            </div>

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
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                    className={accountForm.field}
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
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                    className={accountForm.field}
                                />
                                <InputError
                                    message={errors.password}
                                    className={accountForm.error}
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
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                    className={accountForm.field}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                    className={accountForm.error}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="terms"
                                        name="terms"
                                        required
                                        tabIndex={5}
                                        className={`mt-0.5 ${accountForm.checkbox}`}
                                    />
                                    <Label
                                        htmlFor="terms"
                                        className="leading-snug font-normal"
                                    >
                                        <span>
                                            I agree to the{' '}
                                            <a
                                                href="/terms-conditions"
                                                target="_blank"
                                                rel="noreferrer"
                                                className="font-semibold underline decoration-brand-line underline-offset-4 hover:text-brand-crimson-strong"
                                            >
                                                Terms &amp; Conditions
                                            </a>
                                        </span>
                                    </Label>
                                </div>
                                <InputError
                                    message={errors.terms}
                                    className={accountForm.error}
                                />
                                <p className="text-sm text-muted-foreground">
                                    Our{' '}
                                    <a
                                        href="/privacy-policy"
                                        target="_blank"
                                        rel="noreferrer"
                                        className="font-semibold underline decoration-brand-line underline-offset-4 hover:text-brand-crimson-strong"
                                    >
                                        Privacy Policy
                                    </a>{' '}
                                    explains how we use your personal data.
                                </p>
                            </div>

                            <button
                                type="submit"
                                className={accountForm.submit}
                                tabIndex={6}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </button>
                        </div>

                        <div className={accountForm.footer}>
                            Already have an account?{' '}
                            <TextLink
                                href={login()}
                                tabIndex={6}
                                className="font-semibold"
                            >
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
