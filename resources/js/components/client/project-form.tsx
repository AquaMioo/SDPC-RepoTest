import Field from '@/components/sdpc/field';
import { Panel } from '@/components/sdpc/panel';
import SkillInput from '@/components/sdpc/skill-input';
import { Input } from '@/components/ui/input';
import type { ProjectFormOptions } from '@/types/client';
import { CATEGORIES, INDUSTRIES } from '@/types/client';

export type ProjectFormValues = {
    title: string;
    description: string;
    objectives: string;
    category: string;
    industry: string;
    skills: string[];
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
                <h6 className="m-0">Required skills</h6>

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
            </Panel>
        </>
    );
}
