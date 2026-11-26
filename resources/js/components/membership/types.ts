import type { Option } from './form-bits';

export type StepKey =
    | 'about'
    | 'grade'
    | 'experience'
    | 'route'
    | 'documents'
    | 'review';

export type StepItem = { key: StepKey; label: string; complete: boolean };

export type Publication = {
    title: string;
    venue: string;
    year: string;
    link: string;
};

export type ApplicationData = {
    title: string;
    phone: string;
    state_id: string;
    city: string;
    grade: string;
    highest_qualification: string;
    current_role: string;
    role_type: string;
    currently_studying: string;
    other_evidence: string;
    institution: string;
    department: string;
    years_experience: string;
    publications: Publication[];
    claimed_areas: string[];
    route: string;
    exemption_reason: string;
    exemption_links: string[];
    exam_status: string;
    exam_passed_on: string;
    induction_event: string;
    induction_status: string;
    induction_attended_on: string;
    directory_consent: boolean;
    keep_documents_two_years: boolean;
    state_label: string | null;
};

export type GradeOption = Option & {
    induction_fee: number;
    annual_dues: number;
    induction_usd: number | null;
    annual_usd: number | null;
    criteria: string[];
};

export type DocumentItem = {
    id: number;
    kind: string;
    kind_label: string;
    name: string;
    size: number;
    url: string;
};

export type Options = {
    regions: { name: string; states: Option[] }[];
    grades: GradeOption[];
    rateAttribution: string;
    roleTypes: Option[];
    studying: Option[];
    qualifications: Option[];
    areas: (Option & { advanced: boolean })[];
    routes: Option[];
    examStatuses: Option[];
    inductionEvents: Option[];
    inductionStatuses: Option[];
    documentKinds: Option[];
    maxDocuments: number;
    maxDocumentMb: number;
    maxPublications: number;
};
