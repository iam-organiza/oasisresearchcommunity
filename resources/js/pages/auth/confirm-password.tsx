import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Label } from '@/components/ui/label';
import TextLink from '@/components/text-link';
import { Spinner } from '@/components/ui/spinner';
import { accountForm } from '@/lib/account-form';
import { forgotten } from '@/routes/password';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirm your password" />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="password"
                                className={accountForm.label}
                            >
                                Current password
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="current-password"
                                autoFocus
                                className={accountForm.field}
                            />
                            <InputError
                                message={errors.password}
                                className={accountForm.error}
                            />
                        </div>

                        <button
                            type="submit"
                            className={accountForm.submit}
                            disabled={processing}
                            data-test="confirm-password-button"
                        >
                            {processing && <Spinner />}
                            Continue
                        </button>

                        {/* The way out when the password is the thing forgotten. */}
                        <div className={accountForm.footer}>
                            Forgotten it?{' '}
                            <TextLink
                                href={forgotten()}
                                className="font-semibold"
                            >
                                Log out and reset it
                            </TextLink>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirm your password',
    description:
        'Enter your current password to open your security settings. We ask again from time to time.',
};
