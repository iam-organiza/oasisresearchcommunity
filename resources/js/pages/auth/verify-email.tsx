// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Spinner } from '@/components/ui/spinner';
import { accountForm } from '@/lib/account-form';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div role="status" className={`mb-6 ${accountForm.status}`}>
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <Form {...send.form()} className="flex flex-col gap-6">
                {({ processing }) => (
                    <>
                        <button
                            type="submit"
                            disabled={processing}
                            className={accountForm.submit}
                        >
                            {processing && <Spinner />}
                            Resend verification email
                        </button>

                        <div className={accountForm.footer}>
                            <TextLink href={logout()} className="font-semibold">
                                Log out
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Email verification',
    description:
        'Please verify your email address by clicking on the link we just emailed to you.',
};
