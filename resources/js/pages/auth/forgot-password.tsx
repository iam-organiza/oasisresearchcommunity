import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Notice } from '@/components/notice';
import TextLink from '@/components/text-link';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accountForm } from '@/lib/account-form';
import { login } from '@/routes';
import { email as sendLink } from '@/routes/password';

/**
 * Ask for a reset link. Once sent, the page says where it went (so a typo
 * shows), how long it lasts and what to do if it does not come, without ever
 * saying whether the address has an account.
 */
export default function ForgotPassword({
    email,
    sentTo,
    linkMinutes,
}: {
    email: string;
    sentTo?: string | null;
    linkMinutes: number;
}) {
    return (
        <>
            <Head title="Forgot your password?" />

            {sentTo && (
                <Notice
                    tone="success"
                    title="Check your email"
                    className="mb-6"
                >
                    <p>
                        If there is an account for{' '}
                        <span className="font-semibold break-all text-foreground">
                            {sentTo}
                        </span>
                        , we have sent it a link to choose a new password. The
                        link works for {linkMinutes} minutes.
                    </p>
                    <ul className="mt-2 grid list-disc gap-1 pl-5">
                        <li>Not there? Check your spam or junk folder.</li>
                        <li>Wrong address? Correct it below and send again.</li>
                    </ul>
                </Notice>
            )}

            {/* noValidate: the site's messages instead of the browser's pop-up. */}
            <Form
                {...sendLink.form()}
                noValidate
                className="flex flex-col gap-6"
            >
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
                                autoComplete="email"
                                autoFocus
                                defaultValue={sentTo ?? email}
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
                            {sentTo
                                ? 'Send another link'
                                : 'Send me a reset link'}
                        </button>

                        <div className={accountForm.footer}>
                            Remembered it?{' '}
                            <TextLink href={login()} className="font-semibold">
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Forgot your password?',
    description:
        'Enter the email you use for OASIS Research Community and we will send you a link to choose a new one.',
};
