import { Form, Head } from '@inertiajs/react';
import { Notice } from '@/components/notice';
import TextLink from '@/components/text-link';
import { Spinner } from '@/components/ui/spinner';
import { accountForm } from '@/lib/account-form';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { send } from '@/routes/verification';

/**
 * Straight after signing up, and whenever an unverified account opens a
 * members' page: where the link went, how long it lasts, and what to do if
 * it never came or the address is wrong.
 */
export default function VerifyEmail({
    status,
    email,
    linkMinutes,
}: {
    status?: string;
    email?: string | null;
    linkMinutes: number;
}) {
    return (
        <>
            <Head title="Check your email" />

            {status === 'verification-link-sent' && (
                <Notice tone="success" className="mb-6">
                    We have sent a new link{email ? ` to ${email}` : ''}.
                </Notice>
            )}
            {status === 'verification-link-expired' && (
                <Notice tone="warning" className="mb-6">
                    That link has expired or is incomplete. Send yourself a new
                    one below.
                </Notice>
            )}

            {email && (
                <div className="mb-6 rounded-control border border-border bg-background p-4">
                    <p className="text-sm text-muted-foreground">
                        We sent the link to
                    </p>
                    <p className="mt-0.5 font-semibold break-all">{email}</p>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Wrong address?{' '}
                        <TextLink
                            href={editProfile()}
                            className="font-semibold"
                        >
                            Change it
                        </TextLink>{' '}
                        and we send the link to the new one.
                    </p>
                </div>
            )}

            <ul className="mb-6 grid list-disc gap-1 pl-5 text-sm text-muted-foreground">
                <li>The link works for {linkMinutes} minutes.</li>
                <li>Not there? Check your spam or junk folder.</li>
            </ul>

            <Form {...send.form()} className="flex flex-col gap-6">
                {({ processing }) => (
                    <>
                        <button
                            type="submit"
                            disabled={processing}
                            className={accountForm.submit}
                        >
                            {processing && <Spinner />}
                            Send a new link
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
    title: 'Check your email',
    description:
        'Open the link we emailed you to confirm your address. Then you can apply for membership.',
};
