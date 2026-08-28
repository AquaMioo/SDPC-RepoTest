import { Head, Link, router } from '@inertiajs/react';
import { StarIcon } from '@phosphor-icons/react';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { update as applicationsUpdate } from '@/routes/applications';
import { store as messagesStore } from '@/routes/messages';
import { show as studentsShow } from '@/routes/students';

type Applicant = {
    id: number;
    status: string;
    statusLabel: string;
    isActionable: boolean;
    awaitsStudentDecision: boolean;
    sourceLabel: string;
    coverLetter: string | null;
    proposedRate: number | null;
    student: {
        id: number;
        name: string;
        headline: string | null;
        school: string | null;
        course: string | null;
        yearLevel: number | null;
        rating: number | null;
        completedProjects: number | null;
        skills: string[];
    };
};

type Props = {
    project: { id: number; slug: string; title: string; teamSize: number };
    applications: Applicant[];
};

export default function Applicants({ project, applications }: Props) {
    const team = useCurrentTeam();

    /** Opens the thread for this posting and student, creating it if needed. */
    const message = (application: Applicant) =>
        router.post(messagesStore.url(team.slug), {
            project_id: project.id,
            user_id: application.student.id,
        });

    const respond = (application: Applicant, status: string) => {
        router.patch(
            applicationsUpdate.url({
                current_team: team.slug,
                application: application.id,
            }),
            { status },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Applicants — ${project.title}`} />

            <div className="mx-auto max-w-[1160px] px-4 pt-[30px] pb-[72px] sm:px-6 lg:px-8">
                <div className="mb-5">
                    <h3 className="m-0">Applicants</h3>
                    <div className="text-[13px] text-muted-foreground">
                        {project.title}
                    </div>
                </div>

                {applications.length === 0 ? (
                    <Panel padding="lg" gap="lg" className="items-start">
                        <h6 className="m-0">No applicants yet</h6>
                        <p className="m-0 max-w-[52ch] text-[13px] leading-relaxed text-muted-foreground">
                            Students who apply — and any you invite from the
                            recruit screen — will appear here.
                        </p>
                    </Panel>
                ) : (
                    <div className="flex flex-col gap-4">
                        {applications.map((application) => (
                            <Panel key={application.id} padding="lg" gap="lg">
                                <div className="flex flex-wrap items-start gap-3">
                                    <div className="mr-auto min-w-0">
                                        <Link
                                            href={studentsShow.url({
                                                current_team: team.slug,
                                                user: application.student.id,
                                            })}
                                            className="text-[15px] font-semibold hover:underline"
                                        >
                                            {application.student.name}
                                        </Link>
                                        <div className="text-[12px] text-muted-foreground">
                                            {[
                                                application.student.headline,
                                                application.student.course,
                                                application.student.school,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                    </div>

                                    <Tag variant="outline">
                                        {application.sourceLabel}
                                    </Tag>
                                    <Tag
                                        variant={
                                            application.status === 'accepted'
                                                ? 'accent'
                                                : 'neutral'
                                        }
                                    >
                                        {application.statusLabel}
                                    </Tag>
                                </div>

                                <div className="flex flex-wrap items-center gap-4 text-[12px] text-muted-foreground">
                                    {application.student.rating !== null && (
                                        <span className="flex items-center gap-1.5">
                                            <StarIcon
                                                weight="fill"
                                                className="text-primary"
                                            />
                                            {application.student.rating}
                                        </span>
                                    )}
                                    <span>
                                        {application.student
                                            .completedProjects ?? 0}{' '}
                                        completed
                                    </span>
                                    {application.proposedRate !== null && (
                                        <span>
                                            Proposed rate: ₱
                                            {application.proposedRate}
                                        </span>
                                    )}
                                </div>

                                {application.student.skills.length > 0 && (
                                    <div className="flex flex-wrap gap-1.5">
                                        {application.student.skills.map(
                                            (skill) => (
                                                <Tag
                                                    key={skill}
                                                    variant="neutral"
                                                >
                                                    {skill}
                                                </Tag>
                                            ),
                                        )}
                                    </div>
                                )}

                                {application.coverLetter && (
                                    <>
                                        <PanelKicker>Cover letter</PanelKicker>
                                        <p className="m-0 text-[13px] leading-relaxed opacity-85">
                                            {application.coverLetter}
                                        </p>
                                    </>
                                )}

                                <div className="flex flex-wrap gap-2">
                                    {/* Available whatever the status: a
                                        rejection is often worth a sentence. */}
                                    <Button
                                        variant="outline"
                                        onClick={() => message(application)}
                                    >
                                        Message
                                    </Button>
                                </div>

                                {application.isActionable && (
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                respond(
                                                    application,
                                                    'shortlisted',
                                                )
                                            }
                                        >
                                            Shortlist
                                        </Button>
                                        {/*
                                         * No Accept on an invitation this
                                         * business sent: inviting already
                                         * said yes, and the answer is the
                                         * student's to give.
                                         */}
                                        {!application.awaitsStudentDecision && (
                                            <Button
                                                onClick={() =>
                                                    respond(
                                                        application,
                                                        'accepted',
                                                    )
                                                }
                                            >
                                                Accept
                                            </Button>
                                        )}
                                        <Button
                                            variant="ghost"
                                            onClick={() =>
                                                respond(application, 'rejected')
                                            }
                                        >
                                            {application.awaitsStudentDecision
                                                ? 'Cancel invitation'
                                                : 'Reject'}
                                        </Button>
                                    </div>
                                )}

                                {application.awaitsStudentDecision && (
                                    <p className="text-sm text-muted-foreground">
                                        Waiting for {application.student.name}{' '}
                                        to accept or decline your invitation.
                                    </p>
                                )}
                            </Panel>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
