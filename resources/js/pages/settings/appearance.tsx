import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            {/* Same white panel as Profile and Security; the tiles size themselves to the card. */}
            <section className="@container max-w-3xl rounded-card border border-border bg-background p-6 md:p-8">
                <header className="mb-8">
                    <h2 className="text-heading03 font-semibold">
                        Appearance settings
                    </h2>
                    <p className="mt-1 text-muted-foreground">
                        Update the appearance settings for your account
                    </p>
                </header>

                <AppearanceTabs />
            </section>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
