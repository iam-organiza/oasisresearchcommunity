import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    // A quiet row under the panel: the action is rare, so it does not shout until opened.
    return (
        <section className="flex max-w-3xl flex-col gap-4 border-t border-border pt-8 sm:flex-row sm:items-start sm:justify-between sm:gap-8">
            <div>
                <h2 className="font-semibold">Delete account</h2>
                <p className="mt-1 text-muted-foreground">
                    Delete your account and all of its resources. Please proceed
                    with caution, this cannot be undone.
                </p>
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="outline"
                        className="shrink-0 border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        data-test="delete-user-button"
                    >
                        Delete account
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        Are you sure you want to delete your account?
                    </DialogTitle>
                    <DialogDescription>
                        Once your account is deleted, all of its resources and
                        data will also be permanently deleted. Please enter your
                        password to confirm you would like to permanently delete
                        your account.
                    </DialogDescription>

                    <Form
                        {...ProfileController.destroy.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        onError={() => passwordInput.current?.focus()}
                        resetOnSuccess
                        className="space-y-6"
                    >
                        {({ resetAndClearErrors, processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="password"
                                        className="sr-only"
                                    >
                                        Password
                                    </Label>

                                    <PasswordInput
                                        className="h-11"
                                        id="password"
                                        name="password"
                                        ref={passwordInput}
                                        placeholder="Password"
                                        autoComplete="current-password"
                                    />

                                    <InputError
                                        message={errors.password}
                                        className="font-semibold text-destructive"
                                    />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                resetAndClearErrors()
                                            }
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                        asChild
                                    >
                                        <button
                                            type="submit"
                                            data-test="confirm-delete-user-button"
                                        >
                                            Delete account
                                        </button>
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
