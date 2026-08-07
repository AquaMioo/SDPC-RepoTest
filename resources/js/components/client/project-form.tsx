import Field from '@/components/sdpc/field';
import MilestoneEditor from '@/components/sdpc/milestone-editor';
import { Panel, PanelDivider } from '@/components/sdpc/panel';
import SkillInput from '@/components/sdpc/skill-input';
import ToggleField from '@/components/sdpc/toggle-field';
import { Input } from '@/components/ui/input';
import type { ProjectFormOptions } from '@/types/client';
import {
    CATEGORIES,
    INDUSTRIES,
    TEAM_SIZES,
    WEEKLY_COMMITMENTS,
} from '@/types/client';

export type MilestoneValue = {
    title: string;
    due_date: string | null;
    amount: number | null;
};

export type ProjectFormValues = {
    title: string;
    description: string;
    objectives: string;
    category: string;
    industry: string;
    skills: string[];
    team_size: number;
    experience_level: string;
    open_to_capstone_groups: boolean;
    budget_type: string;
    budget_amount: number | null;
    hide_budget: boolean;
    start_date: string;
    target_delivery_date: string;
    application_deadline: string;
    weekly_commitment: string;
    milestones: MilestoneValue[];
    visibility: string;
    preferred_school_id: number | null;
    preferred_course_id: number | null;
    preferred_year_level: number | null;
    status: string;
};

type Props = {
    data: ProjectFormValues;
    setData: <K extends keyof ProjectFormValues>(
        key: K,
        value: ProjectFormValues[K],
    ) => void;
    errors: Partial<Record<string, string>>;
    options: ProjectFormOptions;
};

const SELECT_CLASSES =
    'h-9 rounded-md border border-input bg-background px-3 text-sm';

/**
 * The posting form's field set, shared by the create and edit screens.
 *
 * Owns no submission behaviour — the pages supply their own useForm instance
 * and action bar, so "post a project" and "edit a posting" can differ in what
 * they submit while presenting identical fields.
 */
export default function ProjectForm({ data, setData, errors, options }: Props) {
    return (
        <>
            <Panel padding="lg" gap="lg">
                <h6 className="m-0">The basics</h6>

                <Field label="Project title" error={errors.title} required>
                    {(props) => (
                        <Input
                            {...props}
                            value={data.title}
                            placeholder="e.g. Inventory system with predictive reorder alerts"
                            onChange={(e) => setData('title', e.target.value)}
                        />
                    )}
                </Field>

                <div className="grid gap-3.5 sm:grid-cols-2">
                    <Field label="Category" error={errors.category} required>
                        {(props) => (
                            <select
                                {...props}
                                className={SELECT_CLASSES}
                                value={data.category}
                                onChange={(e) =>
                                    setData('category', e.target.value)
                                }
                            >
                                {CATEGORIES.map((category) => (
                                    <option key={category}>{category}</option>
                                ))}
                            </select>
                        )}
                    </Field>

                    <Field label="Industry" error={errors.industry}>
                        {(props) => (
                            <select
                                {...props}
                                className={SELECT_CLASSES}
                                value={data.industry}
                                onChange={(e) =>
                                    setData('industry', e.target.value)
                                }
                            >
                                {INDUSTRIES.map((industry) => (
                                    <option key={industry}>{industry}</option>
                                ))}
                            </select>
                        )}
                    </Field>
                </div>

                <Field
                    label="Project description"
                    error={errors.description}
                    required
                >
                    {(props) => (
                        <textarea
                            {...props}
                            className="min-h-[120px] rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={data.description}
                            placeholder="What problem should the system solve? Who will use it, and what does success look like at turnover?"
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                    )}
                </Field>

                <Field
                    label="Objectives"
                    hint="One per line."
                    error={errors.objectives}
                >
                    {(props) => (
                        <textarea
                            {...props}
                            className="min-h-[78px] rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={data.objectives}
                            placeholder={
                                'Replace the spreadsheet used across three branches\nAlert staff before stock runs out'
                            }
                            onChange={(e) =>
                                setData('objectives', e.target.value)
                            }
                        />
                    )}
                </Field>
            </Panel>

            <Panel padding="lg" gap="lg">
                <h6 className="m-0">Skills &amp; team</h6>

                <Field label="Required skills" error={errors.skills}>
                    {(props) => (
                        <SkillInput
                            {...props}
                            value={data.skills}
                            suggestions={options.skills}
                            onChange={(skills) => setData('skills', skills)}
                        />
                    )}
                </Field>

                <div className="grid gap-3.5 sm:grid-cols-2">
                    <Field label="Team size needed" error={errors.team_size}>
                        {(props) => (
                            <select
                                {...props}
                                className={SELECT_CLASSES}
                                value={data.team_size}
                                onChange={(e) =>
                                    setData('team_size', Number(e.target.value))
                                }
                            >
                                {TEAM_SIZES.map((size) => (
                                    <option key={size.value} value={size.value}>
                                        {size.label}
                                    </option>
                                ))}
                            </select>
                        )}
                    </Field>

                    <Field
                        label="Experience level"
                        error={errors.experience_level}
                    >
                        {(props) => (
                            <select
                                {...props}
                                className={SELECT_CLASSES}
                                value={data.experience_level}
                                onChange={(e) =>
                                    setData('experience_level', e.target.value)
                                }
                            >
                                {options.experienceLevels.map((level) => (
                                    <option key={level.value} value={level.value}>
                                        {level.label}
                                    </option>
                                ))}
                            </select>
                        )}
                    </Field>
                </div>

                <ToggleField
                    label="Open to capstone groups"
                    description="Allow this project to be claimed as an academic requirement"
                    checked={data.open_to_capstone_groups}
                    onChange={(e) =>
                        setData('open_to_capstone_groups', e.target.checked)
                    }
                />
            </Panel>

            <Panel padding="lg" gap="lg">
                <h6 className="m-0">Timeline &amp; budget</h6>

                <div className="grid gap-3.5 sm:grid-cols-3">
                    <Field label="Preferred start" error={errors.start_date}>
                        {(props) => (
                            <Input
                                {...props}
                                type="date"
                                value={data.start_date}
                                onChange={(e) =>
                                    setData('start_date', e.target.value)
                                }
                            />
                        )}
                    </Field>

                    <Field
                        label="Target delivery"
                        error={errors.target_delivery_date}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                type="date"
                                value={data.target_delivery_date}
                                onChange={(e) =>
                                    setData(
                                        'target_delivery_date',
                                        e.target.value,
                                    )
                                }
                            />
                        )}
                    </Field>

                    <Field
                        label="Application deadline"
                        error={errors.application_deadline}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                type="date"
                                value={data.application_deadline}
                                onChange={(e) =>
                                    setData(
                                        'application_deadline',
                                        e.target.value,
                                    )
                                }
                            />
                        )}
                    </Field>
                </div>

                <div className="grid gap-3.5 sm:grid-cols-3">
                    <Field
                        label="Weekly commitment"
                        error={errors.weekly_commitment}
                    >
                        {(props) => (
                            <select
                                {...props}
                                className={SELECT_CLASSES}
                                value={data.weekly_commitment}
                                onChange={(e) =>
                                    setData('weekly_commitment', e.target.value)
                                }
                            >
                                {WEEKLY_COMMITMENTS.map((option) => (
                                    <option key={option}>{option}</option>
                                ))}
                            </select>
                        )}
                    </Field>

                    <Field label="Budget type" error={errors.budget_type}>
                        {(props) => (
                            <div
                                {...props}
                                className="flex w-full rounded-md border border-input p-0.5"
                            >
                                {options.budgetTypes.map((type) => (
                                    <button
                                        key={type.value}
                                        type="button"
                                        onClick={() =>
                                            setData('budget_type', type.value)
                                        }
                                        aria-pressed={
                                            data.budget_type === type.value
                                        }
                                        className={
                                            'flex-1 rounded-[6px] px-3 py-1 text-xs transition-colors ' +
                                            (data.budget_type === type.value
                                                ? 'bg-primary/14 text-primary'
                                                : 'text-muted-foreground hover:text-foreground')
                                        }
                                    >
                                        {type.label}
                                    </button>
                                ))}
                            </div>
                        )}
                    </Field>

                    <Field
                        label="Total budget (PHP)"
                        error={errors.budget_amount}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                type="number"
                                min={0}
                                value={data.budget_amount ?? ''}
                                placeholder="₱ 28,000"
                                onChange={(e) =>
                                    setData(
                                        'budget_amount',
                                        e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    )
                                }
                            />
                        )}
                    </Field>
                </div>

                <MilestoneEditor
                    value={data.milestones}
                    onChange={(milestones) => setData('milestones', milestones)}
                    errors={errors as Record<string, string>}
                />
            </Panel>

            <Panel padding="lg" gap="lg">
                <h6 className="m-0">Visibility</h6>

                <Field
                    label="Who can see this posting"
                    error={errors.visibility}
                >
                    {(props) => (
                        <select
                            {...props}
                            className={SELECT_CLASSES}
                            value={data.visibility}
                            onChange={(e) =>
                                setData('visibility', e.target.value)
                            }
                        >
                            {options.visibilities.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    )}
                </Field>

                <div className="grid gap-3.5 sm:grid-cols-2">
                    <Field
                        label="Preferred school"
                        error={errors.preferred_school_id}
                    >
                        {(props) => (
                            <select
                                {...props}
                                className={SELECT_CLASSES}
                                value={data.preferred_school_id ?? ''}
                                onChange={(e) =>
                                    setData(
                                        'preferred_school_id',
                                        e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    )
                                }
                            >
                                <option value="">No preference</option>
                                {options.schools.map((school) => (
                                    <option key={school.id} value={school.id}>
                                        {school.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    </Field>

                    <Field
                        label="Preferred course"
                        error={errors.preferred_course_id}
                    >
                        {(props) => (
                            <select
                                {...props}
                                className={SELECT_CLASSES}
                                value={data.preferred_course_id ?? ''}
                                onChange={(e) =>
                                    setData(
                                        'preferred_course_id',
                                        e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    )
                                }
                            >
                                <option value="">No preference</option>
                                {options.courses.map((course) => (
                                    <option key={course.id} value={course.id}>
                                        {course.abbreviation ?? course.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    </Field>
                </div>

                <PanelDivider />

                <ToggleField
                    label="Hide my budget from the public listing"
                    description="Only students you shortlist will see the amount"
                    checked={data.hide_budget}
                    onChange={(e) => setData('hide_budget', e.target.checked)}
                />
            </Panel>
        </>
    );
}
