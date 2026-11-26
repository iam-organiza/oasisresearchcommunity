import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import {
    Checkbox,
    Field,
    selectClass,
} from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index, store } from '@/routes/admin/members';

type Option = { value: string; label: string };

export default function RecordMember({
    grades,
    regions,
}: {
    grades: Option[];
    regions: { name: string; states: Option[] }[];
}) {
    const form = useForm({
        email: '',
        grade: '',
        state_id: '',
        number: '',
        joined_on: '',
        induction_paid: false,
        fee_waived: false,
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
                                onChange={(e) =>
                                    setData('grade', e.target.value)
                                }
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
                    <div className="grid gap-3">
                        <Checkbox
                            id="induction_paid"
                            checked={data.induction_paid}
                            onChange={(checked) =>
                                setData('induction_paid', checked)
                            }
                        >
                            Their induction fee has been received.
                        </Checkbox>
                        <Checkbox
                            id="fee_waived"
                            checked={data.fee_waived}
                            onChange={(checked) =>
                                setData('fee_waived', checked)
                            }
                        >
                            Their induction fee is waived (for example a
                            scholarship).
                        </Checkbox>
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
