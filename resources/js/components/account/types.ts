export type StatementPayment = {
    id: number;
    receipt_number: string;
    amount: number;
    method: string;
    reference: string | null;
    paid_on: string;
    note: string | null;
    recorded_by: string | null;
    voided: { reason: string | null; on: string | null } | null;
    receipt_url: string | null;
};

export type StatementCharge = {
    id: number;
    label: string;
    kind: string;
    amount: number;
    balance: number;
    paid: number;
    status: string;
    added_on: string;
    waiver: {
        type: string;
        value: number | null;
        description: string | null;
        amount: number;
        reason: string | null;
        by: string | null;
        on: string | null;
    } | null;
    cancelled: { reason: string | null; on: string | null } | null;
    payments: StatementPayment[];
};

export type Statement = {
    totals: {
        owed: number;
        owed_usd: number | null;
        paid: number;
        waived: number;
    };
    charges: StatementCharge[];
    history: {
        id: number;
        label: string;
        charge: string | null;
        receipt_number: string | null;
        amount: number | null;
        note: string | null;
        who: string | null;
        when: string;
    }[];
};
