import { Head, Link } from '@inertiajs/react';
import { BalanceStrip, ChargeList } from '@/components/account/statement';
import type { Statement } from '@/components/account/types';
import Heading from '@/components/heading';
import { account, show } from '@/routes/membership';

export default function MembershipAccount({
    statement,
    member,
}: {
    statement: Statement;
    member: { number: string; active: boolean; status: string } | null;
}) {
    const description = member
        ? `${member.number} · ${member.status}`
        : 'Fees appear here once you have something to pay, such as the exam fee or, after approval, your induction fee.';

    return (
        <>
            <Head title="Fees and payments" />
            <div className="@container px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading title="Fees and payments" description={description} />

                <div className="grid max-w-5xl gap-6">
                    <BalanceStrip
                        totals={statement.totals}
                        owedLabel="You owe"
                    />
                    <ChargeList
                        charges={statement.charges}
                        empty={
                            <>
                                Nothing to pay yet.{' '}
                                <Link
                                    href={show()}
                                    className="font-semibold text-foreground underline decoration-border underline-offset-4 hover:decoration-current"
                                >
                                    See your membership
                                </Link>
                            </>
                        }
                    />
                    <p className="max-w-prose text-sm text-muted-foreground">
                        Online payment is coming. Until then, pay by bank
                        transfer as the Secretariat tells you; the Registrar
                        records it here and your receipt arrives by email. Each
                        receipt number above opens a copy you can print.
                    </p>
                </div>
            </div>
        </>
    );
}

MembershipAccount.layout = {
    breadcrumbs: [{ title: 'Fees and payments', href: account() }],
};
