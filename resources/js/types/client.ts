export type SelectOption = {
    value: string;
    label: string;
};

export type ProjectFormOptions = {
    budgetTypes: SelectOption[];
    experienceLevels: SelectOption[];
    visibilities: SelectOption[];
    schools: { id: number; name: string }[];
    courses: { id: number; name: string; abbreviation: string | null }[];
    skills: { name: string; type: string }[];
};

export type ProjectListItem = {
    slug: string;
    title: string;
    category: string;
    status: string;
    statusLabel: string;
    budgetAmount: number | null;
    applicationsOpen: boolean;
    applicationsCount: number;
    pendingApplicationsCount: number;
    targetDeliveryDate: string | null;
    skills: string[];
};

export type StudentCard = {
    id: number;
    name: string;
    headline: string | null;
    school: string | null;
    course: string | null;
    yearLevel: number | null;
    rating: number;
    completedProjects: number;
    hourlyRate: number | null;
    isAvailable: boolean;
    skills: string[];
    compatibility: number | null;
};

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};

/*
 * Mirrors the option lists the design's posting form offers.
 *
 * Typed as readonly string[] rather than `as const`: these feed free-text
 * columns, so the form state is a plain string and a narrowed literal union
 * would just fight every assignment.
 */
export const CATEGORIES: readonly string[] = [
    'Web application',
    'Mobile application',
    'Management / inventory system',
    'E-commerce or booking',
    'Data & analytics',
];

export const INDUSTRIES: readonly string[] = [
    'Retail & grocery',
    'Logistics',
    'Food service',
    'Healthcare',
    'Education',
    'Other',
];

export const WEEKLY_COMMITMENTS: readonly string[] = [
    'Up to 10 hrs',
    '10–20 hrs',
    '20+ hrs',
];

export const TEAM_SIZES = [
    { value: 1, label: '1 student' },
    { value: 3, label: '2–3 students' },
    { value: 5, label: '4–5 students (capstone group)' },
] as const;

type CompletionInput = {
    title: string;
    description: string;
    objectives: string;
    skills: string[];
    budget_amount: number | null;
    target_delivery_date: string;
    milestones: unknown[];
};

/**
 * Drives the posting form's completeness meter.
 *
 * Deliberately client-side and advisory: it reflects brief quality, not
 * validity, so it must not be confused with the server's validation rules.
 */
export function projectFormCompletion(data: CompletionInput): {
    percentage: number;
    checklist: { label: string; done: boolean }[];
} {
    const checklist = [
        {
            label: 'Title and description written',
            done: Boolean(data.title && data.description),
        },
        {
            label: 'Describe the users of the system',
            done: data.description.length >= 160,
        },
        { label: 'List the objectives', done: Boolean(data.objectives.trim()) },
        { label: 'Add the required skills', done: data.skills.length > 0 },
        { label: 'Set a budget', done: data.budget_amount !== null },
        {
            label: 'Set a target delivery date',
            done: Boolean(data.target_delivery_date),
        },
        {
            label: 'Break the work into milestones',
            done: data.milestones.length > 0,
        },
    ];

    const done = checklist.filter((item) => item.done).length;

    return {
        percentage: Math.round((done / checklist.length) * 100),
        checklist,
    };
}
