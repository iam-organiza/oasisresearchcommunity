import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import {
    ChoiceTiles,
    Field,
    naira,
    selectClass,
} from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index, store } from '@/routes/admin/members';

type Option = { value: string; label: string };
type GradeOption = Option & { induction_fee: number };

export default function RecordMember({
    grades,
    regions,
}: {
    grades: GradeOption[];
    regions: { name: string; states: Option[] }[];
}) {
    const form = useForm({
        email: '',
        grade: '',
        state_id: '',
        number: '',
        joined_on: '',
        induction: '',
        induction_amount: '',
    });
    const { data, setData, errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url());
    };

    return (
        <>
            <Head title="Record an existing member" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Record an existing member"
                    description="For people admitted before the website. They need an account first; no application is needed."
                />

                <form
                    onSubmit={submit}
                    className="@container grid max-w-3xl gap-6 rounded-card border border-border bg-background p-6 md:p-8"
                    noValidate
                >
                    <Field
                        id="email"
                        label="Email address of their account"
                        error={errors.email}
                    >
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            aria-invalid={errors.email ? true : undefined}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </Field>
                    <div className="grid gap-6 @xl:grid-cols-2">
                        <Field id="grade" label="Grade" error={errors.grade}>
                            <select
                                id="grade"
                                value={data.grade}
                                aria-invalid={errors.grade ? true : undefined}
                                onChange={(e) => {
                                    const fee = grades.find(
                                        (grade) =>
                                            grade.value === e.target.value,
                                    )?.induction_fee;
                                    form.setData({
                                        ...data,
                                        grade: e.target.value,
                                        induction_amount: fee
                                            ? String(fee)
                                            : '',
                                    });
                                }}
                                className={selectClass}
                            >
                                <option value="">Choose a grade</option>
                                {grades.map((grade) => (
                                    <option
                                        key={grade.value}
                                        value={grade.value}
                                    >
                                        {grade.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            id="state_id"
                            label="State"
                            error={errors.state_id}
                            optional
                        >
                            <select
                                id="state_id"
                                value={data.state_id}
                                onChange={(e) =>
                                    setData('state_id', e.target.value)
                                }
                                className={selectClass}
                            >
                                <option value="">Not known</option>
                                {regions.map((region) => (
                                    <optgroup
                                        key={region.name}
                                        label={region.name}
                                    >
                                        {region.states.map((state) => (
                                            <option
                                                key={state.value}
                                                value={state.value}
                                            >
                                                {state.label}
                                            </option>
                                        ))}
                                    </optgroup>
                                ))}
                            </select>
                        </Field>
                        <Field
                            id="number"
                            label="Membership number"
                            hint="Leave empty to create the next number, for example OASIS/MEM/2024/0003."
                            error={errors.number}
                            optional
                        >
                            <Input
                                id="number"
                                value={data.number}
                                aria-invalid={errors.number ? true : undefined}
                                onChange={(e) =>
                                    setData(
                                        'number',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id="joined_on"
                            label="Member since"
                            error={errors.joined_on}
                        >
                            <Input
                                id="joined_on"
                                type="date"
                                value={data.joined_on}
                                aria-invalid={
                                    errors.joined_on ? true : undefined
                                }
                                onChange={(e) =>
                                    setData('joined_on', e.target.value)
                                }
                            />
                        </Field>
                    </div>
                    <ChoiceTiles
                        name="induction"
                        legend="Their induction fee"
                        options={[
                            { value: 'paid', label: 'Paid before the website' },
                            { value: 'owed', label: 'Still owed' },
                            { value: 'waived', label: 'Waived' },
                        ]}
                        value={data.induction}
                        onChange={(value) => setData('induction', value)}
                        error={errors.induction}
                        describe={(option) =>
                            ({
                                paid: 'Recorded as received, without emailing a receipt.',
                                owed: 'Added to their account to pay.',
                                waived: 'For example a scholarship. They are active straight away.',
                            })[option.value]
                        }
                    />
                    <div className="max-w-xs">
                        <Field
                            id="induction_amount"
                            label="Induction fee, in naira"
                            hint={
                                data.grade
                                    ? `Today's fee for this grade is ${naira(grades.find((grade) => grade.value === data.grade)?.induction_fee ?? 0)}. Change it if they joined at an older fee.`
                                    : 'Choose a grade first.'
                            }
                            error={errors.induction_amount}
                        >
                            <Input
                                id="induction_amount"
                                inputMode="numeric"
                                value={data.induction_amount}
                                aria-invalid={
                                    errors.induction_amount ? true : undefined
                                }
                                onChange={(e) =>
                                    setData(
                                        'induction_amount',
                                        e.target.value.replace(/\D/g, ''),
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button
                            type="submit"
                            size="lg"
                            disabled={form.processing}
                        >
                            Add to the register
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <Link href={index()}>Back to the register</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

RecordMember.layout = {
    breadcrumbs: [
        { title: 'Member register', href: index() },
        { title: 'Record an existing member', href: create() },
    ],
};
