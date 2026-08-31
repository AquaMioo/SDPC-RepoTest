import { Head, useForm } from '@inertiajs/react';
import ProjectForm from '@/components/client/project-form';
import type { ProjectFormValues } from '@/components/client/project-form';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { update as projectsUpdate } from '@/routes/projects';
import { INDUSTRIES } from '@/types/client';

type Props = {
    project: {
        slug: string;
        title: string;
        description: string;
        objectives: string | null;
        category: string;
        industry: string | null;
        status: string;
        statusLabel: string;
        skills: string[];
    };
};

export default function EditProject({ project }: Props) {
    const team = useCurrentTeam();

    const { data, setData, patch, transform, processing, errors, isDirty } =
        useForm<ProjectFormValues>({
            title: project.title,
            description: project.description,
            objectives: project.objectives ?? '',
            category: project.category,
            industry: project.industry ?? INDUSTRIES[0],
            skills: project.skills,
            /**
             * Editing never sends a posting backwards into draft, so anything
             * already submitted stays submitted. A draft is the exception:
             * it can go either way, and which one is chosen by the button.
             */
            status: project.status === 'draft' ? 'draft' : 'pending_review',
        });

    const isDraft = project.status === 'draft';

    /**
     * Save, optionally moving a draft forward.
     *
     * useForm holds the status in state, and setData is asynchronous, so the
     * next status is passed to transform() instead — otherwise pressing
     * Publish sends the value the form had before the click, which is exactly
     * how a draft became impossible to publish.
     */
    const save = (status: 'draft' | 'pending_review') => {
        transform((values) => ({ ...values, status }));

        patch(
            projectsUpdate.url({
                current_team: team.slug,
                project: project.slug,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Edit — ${project.title}`} />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    save(isDraft ? 'draft' : 'pending_review');
                }}
                className="mx-auto flex max-w-[clamp(1100px,100vw_-_320px,1600px)] flex-col gap-4 px-4 pt-[30px] pb-[72px] sm:px-6 lg:px-8"
            >
                <div className="flex items-end gap-4">
                    <div className="mr-auto">
                        <h3 className="m-0">Edit posting</h3>
                        <div className="text-[13px] text-muted-foreground">
                            {project.title}
                        </div>
                    </div>
                    <Tag variant="outline">
                        {isDirty ? 'Unsaved changes' : project.statusLabel}
                    </Tag>
                </div>

                <ProjectForm data={data} setData={setData} errors={errors} />

                <div className="flex flex-wrap items-center gap-2.5">
                    {isDraft && (
                        <span className="mr-auto text-[12.5px] text-muted-foreground">
                            This posting is a draft. Students cannot see it
                            until you publish it.
                        </span>
                    )}

                    <Button
                        type="submit"
                        variant={isDraft ? 'secondary' : 'default'}
                        disabled={processing}
                        className={isDraft ? 'px-5' : 'ml-auto px-5'}
                    >
                        {processing
                            ? 'Saving…'
                            : isDraft
                              ? 'Save draft'
                              : 'Save changes'}
                    </Button>

                    {/* The way out of draft. Without this a saved draft could
                        only ever be saved again. */}
                    {isDraft && (
                        <Button
                            type="button"
                            disabled={processing}
                            className="px-5"
                            onClick={() => save('pending_review')}
                        >
                            {processing ? 'Publishing…' : 'Publish'}
                        </Button>
                    )}
                </div>
            </form>
        </>
    );
}
