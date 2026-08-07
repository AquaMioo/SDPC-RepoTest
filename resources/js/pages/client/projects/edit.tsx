import { Head, useForm } from '@inertiajs/react';
import ProjectForm from '@/components/client/project-form';
import type { ProjectFormValues } from '@/components/client/project-form';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { update as projectsUpdate } from '@/routes/projects';
import type { ProjectFormOptions } from '@/types/client';
import { INDUSTRIES, WEEKLY_COMMITMENTS } from '@/types/client';

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
        budgetType: string;
        budgetAmount: number | null;
        hideBudget: boolean;
        startDate: string | null;
        targetDeliveryDate: string | null;
        applicationDeadline: string | null;
        weeklyCommitment: string | null;
        teamSize: number;
        experienceLevel: string;
        openToCapstoneGroups: boolean;
        visibility: string;
        preferredSchoolId: number | null;
        preferredCourseId: number | null;
        preferredYearLevel: number | null;
        skills: string[];
        milestones: {
            title: string;
            dueDate: string | null;
            amount: number | null;
        }[];
    };
    options: ProjectFormOptions;
};

export default function EditProject({ project, options }: Props) {
    const team = useCurrentTeam();

    const { data, setData, patch, processing, errors, isDirty } =
        useForm<ProjectFormValues>({
            title: project.title,
            description: project.description,
            objectives: project.objectives ?? '',
            category: project.category,
            industry: project.industry ?? INDUSTRIES[0],
            skills: project.skills,
            team_size: project.teamSize,
            experience_level: project.experienceLevel,
            open_to_capstone_groups: project.openToCapstoneGroups,
            budget_type: project.budgetType,
            budget_amount: project.budgetAmount,
            hide_budget: project.hideBudget,
            start_date: project.startDate ?? '',
            target_delivery_date: project.targetDeliveryDate ?? '',
            application_deadline: project.applicationDeadline ?? '',
            weekly_commitment: project.weeklyCommitment ?? WEEKLY_COMMITMENTS[0],
            milestones: project.milestones.map((milestone) => ({
                title: milestone.title,
                due_date: milestone.dueDate,
                amount: milestone.amount,
            })),
            visibility: project.visibility,
            preferred_school_id: project.preferredSchoolId,
            preferred_course_id: project.preferredCourseId,
            preferred_year_level: project.preferredYearLevel,
            /** Editing never sends a posting backwards into draft. */
            status: project.status === 'draft' ? 'draft' : 'pending_review',
        });

    return (
        <>
            <Head title={`Edit — ${project.title}`} />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    patch(
                        projectsUpdate.url({
                            current_team: team.slug,
                            project: project.slug,
                        }),
                        { preserveScroll: true },
                    );
                }}
                className="mx-auto flex max-w-[1100px] flex-col gap-4 px-8 pt-[30px] pb-[72px]"
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

                <ProjectForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    options={options}
                />

                <div className="flex items-center gap-2.5">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="ml-auto px-5"
                    >
                        {processing ? 'Saving…' : 'Save changes'}
                    </Button>
                </div>
            </form>
        </>
    );
}
