import { Head, Link } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { accountForm } from '@/lib/account-form';
import { login } from '@/routes';
import { request } from '@/routes/password';

/**
 * A reset link that has expired, been used or been replaced by a newer one:
 * said as the page opens, with the way to a new link.
 */
export default function ResetLinkExpired({ email }: { email: string }) {
    return (
        <>
            <Head title="This link no longer works" />

            <ul className="mb-6 grid list-disc gap-1 pl-5 text-sm text-muted-foreground">
                <li>A reset link works for a limited time, and only once.</li>
                <li>Asking for a new link replaces any older one.</li>
            </ul>

            <div className="flex flex-col gap-6">
                <Link
                    href={request({ query: email ? { email } : {} })}
                    className={accountForm.submit}
                >
                    Send me a new link
                </Link>
                <div className={accountForm.footer}>
                    Remembered it?{' '}
                    <TextLink href={login()} className="font-semibold">
                        Log in
                    </TextLink>
                </div>
            </div>
        </>
    );
}

ResetLinkExpired.layout = {
    title: 'This link no longer works',
    description:
        'The password reset link has expired or has already been used. Send yourself a new one.',
};
