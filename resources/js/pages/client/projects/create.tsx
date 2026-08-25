import { Head, useForm } from '@inertiajs/react';
import {
    CheckCircleIcon,
    CircleIcon,
    SparkleIcon,
} from '@phosphor-icons/react';
import ProjectForm from '@/components/client/project-form';
import type { ProjectFormValues } from '@/components/client/project-form';
import Meter from '@/components/sdpc/meter';
import { Panel, PanelAccent, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { store } from '@/routes/projects';
import type { ProjectFormOptions } from '@/types/client';
import { CATEGORIES, INDUSTRIES, projectFormCompletion } from '@/types/client';

type Props = {
    options: ProjectFormOptions;
    /** False when projects.auto_approve skips the admin review queue. */
    reviewedBeforeGoingLive: boolean;
};

export default function CreateProject({
    options,
    reviewedBeforeGoingLive,
}: Props) {
    const team = useCurrentTeam();

    const form = useForm<ProjectFormValues>({
        title: '',
        description: '',
        objectives: '',
        category: CATEGORIES[0],
        industry: INDUSTRIES[0],
        skills: [],
        status: 'pending_review',
    });

    const { data, setData, errors, processing } = form;
    const completion = projectFormCompletion(data);

    const submit = (status: 'draft' | 'pending_review') => {
        form.transform((payload) => ({ ...payload, status }));
        form.post(store.url(team.slug), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Post a project" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit('pending_review');
                }}
                className="mx-auto grid max-w-[1100px] items-start gap-6 px-4 pt-[30px] pb-[72px] sm:px-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:px-8"
            >
                <div className="flex min-w-0 flex-col gap-4">
                    <div className="flex items-end gap-4">
                        <div className="mr-auto">
                            <h3 className="m-0">Post a project</h3>
                            <div className="text-[13px] text-muted-foreground">
                                Describe the system you need built — matching
                                runs the moment you publish.
                            </div>
                        </div>
                        <Tag variant="outline">
                            {form.isDirty ? 'Unsaved changes' : 'Draft'}
                        </Tag>
                    </div>

                    <ProjectForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        options={options}
                    />

                    <div className="flex flex-wrap items-center gap-2.5">
                        <span className="mr-auto text-[11.5px] text-muted-foreground">
                            {reviewedBeforeGoingLive
                                ? 'Postings are screened by the platform admin before they go live.'
                                : 'Publishing puts this straight on the student board.'}
                        </span>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={processing}
                            onClick={() => submit('draft')}
                        >
                            Save draft
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="px-5"
                        >
                            {processing
                                ? 'Publishing…'
                                : 'Publish & see matches'}
                        </Button>
                    </div>
                </div>

                <aside className="sticky top-[88px] flex flex-col gap-4">
                    <PanelAccent>
                        <div className="flex items-center gap-2">
                            <SparkleIcon className="text-primary" />
                            <PanelKicker className="mr-auto">
                                Live preview of matching
                            </PanelKicker>
                        </div>

                        <p className="m-0 text-[12.5px] leading-relaxed opacity-85">
                            {reviewedBeforeGoingLive
                                ? 'Matching runs once an administrator approves the posting.'
                                : 'Matching runs as soon as you publish.'}{' '}
                            A fuller brief produces stronger matches — the words
                            you use here are what students are ranked against.
                        </p>

                        <Meter
                            label="Posting completeness"
                            value={completion.percentage}
                        />

                        <div className="flex flex-col gap-1.5 text-xs text-muted-foreground">
                            {completion.checklist.map((item) => (
                                <div key={item.label} className="flex gap-2">
                                    {item.done ? (
                                        <CheckCircleIcon
                                            weight="fill"
                                            className="shrink-0 text-primary"
                                        />
                                    ) : (
                                        <CircleIcon className="shrink-0 opacity-50" />
                                    )}
                                    {item.label}
                                </div>
                            ))}
                        </div>
                    </PanelAccent>

                    <Panel padding="lg">
                        <h6 className="m-0">Writing a good brief</h6>
                        <div className="flex flex-col gap-2 text-[12.5px] leading-relaxed text-muted-foreground">
                            <div>
                                Name the process the system replaces — students
                                scope faster from a real workflow than from a
                                feature list.
                            </div>
                            <div>
                                Say who will use it daily; it decides how much
                                training the turnover needs.
                            </div>
                            <div>
                                Keep the brief tight. Fewer, clearer checkpoints
                                are easier to approve.
                            </div>
                        </div>
                    </Panel>
                </aside>
            </form>
        </>
    );
}
