import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';

type Props = {
    passwordRules: string;
};

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Security settings" />

            <h1 className="sr-only">Security settings</h1>

            {/* Same white panel as Profile. The form is split in two: prove it's you, then choose the new password.
                Side by side only once the card itself is wide enough. */}
            <section className="@container max-w-3xl rounded-card border border-border bg-background p-6 md:p-8">
                <header className="mb-8">
                    <h2 className="text-heading03 font-semibold">
                        Update password
                    </h2>
                    <p className="mt-1 text-muted-foreground">
                        Ensure your account is using a long, random password to
                        stay secure
                    </p>
                </header>

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-6 @xl:grid-cols-2">
                                <div className="grid content-start gap-2">
                                    <Label
                                        htmlFor="current_password"
                                        className="font-semibold"
                                    >
                                        Current password
                                    </Label>

                                    <PasswordInput
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        className="w-full"
                                        autoComplete="current-password"
                                        placeholder="Current password"
                                    />

                                    <InputError
                                        className="font-semibold text-destructive"
                                        message={errors.current_password}
                                    />
                                </div>
                            </div>

                            <div className="my-8 border-t border-border" />

                            <div className="grid gap-6 @xl:grid-cols-2">
                                <div className="grid content-start gap-2">
                                    <Label
                                        htmlFor="password"
                                        className="font-semibold"
                                    >
                                        New password
                                    </Label>

                                    <PasswordInput
                                        id="password"
                                        ref={passwordInput}
                                        name="password"
                                        className="w-full"
                                        autoComplete="new-password"
                                        placeholder="New password"
                                        passwordrules={props.passwordRules}
                                    />

                                    <InputError
                                        className="font-semibold text-destructive"
                                        message={errors.password}
                                    />
                                </div>

                                <div className="grid content-start gap-2">
                                    <Label
                                        htmlFor="password_confirmation"
                                        className="font-semibold"
                                    >
                                        Confirm password
                                    </Label>

                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        className="w-full"
                                        autoComplete="new-password"
                                        placeholder="Confirm password"
                                        passwordrules={props.passwordRules}
                                    />

                                    <InputError
                                        className="font-semibold text-destructive"
                                        message={errors.password_confirmation}
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-4 pt-8">
                                <Button
                                    size="lg"
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </section>
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Security settings',
            href: edit(),
        },
    ],
};
