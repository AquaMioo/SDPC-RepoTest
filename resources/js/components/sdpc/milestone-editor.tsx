import { PlusIcon, TrashIcon } from '@phosphor-icons/react';
import { PanelDivider } from '@/components/sdpc/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type MilestoneDraft = {
    title: string;
    due_date: string | null;
    amount: number | null;
};

type MilestoneEditorProps = {
    value: MilestoneDraft[];
    onChange: (milestones: MilestoneDraft[]) => void;
    errors?: Record<string, string>;
};

/**
 * The design's milestone repeater — a title, due date and amount per row.
 */
export default function MilestoneEditor({
    value,
    onChange,
    errors = {},
}: MilestoneEditorProps) {
    const update = (index: number, patch: Partial<MilestoneDraft>) => {
        onChange(
            value.map((milestone, i) =>
                i === index ? { ...milestone, ...patch } : milestone,
            ),
        );
    };

    return (
        <>
            <PanelDivider />

            <div className="flex items-center">
                <span className="mr-auto text-[13.5px]">Milestones</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-[12.5px]"
                    onClick={() =>
                        onChange([
                            ...value,
                            { title: '', due_date: null, amount: null },
                        ])
                    }
                >
                    <PlusIcon />
                    Add milestone
                </Button>
            </div>

            {value.length === 0 ? (
                <p className="text-[12px] text-muted-foreground">
                    No milestones yet. Three or four large checkpoints are
                    easier to approve than many small ones.
                </p>
            ) : (
                <div className="flex flex-col gap-2">
                    {value.map((milestone, index) => (
                        <div key={index} className="flex flex-col gap-1">
                            <div className="grid grid-cols-[1fr_130px_120px_28px] items-center gap-2.5">
                                <Input
                                    value={milestone.title}
                                    placeholder="Design approval"
                                    aria-label={`Milestone ${index + 1} title`}
                                    aria-invalid={
                                        `milestones.${index}.title` in errors
                                    }
                                    onChange={(event) =>
                                        update(index, {
                                            title: event.target.value,
                                        })
                                    }
                                />
                                <Input
                                    type="date"
                                    value={milestone.due_date ?? ''}
                                    aria-label={`Milestone ${index + 1} due date`}
                                    onChange={(event) =>
                                        update(index, {
                                            due_date:
                                                event.target.value || null,
                                        })
                                    }
                                />
                                <Input
                                    type="number"
                                    min={0}
                                    value={milestone.amount ?? ''}
                                    placeholder="₱ 8,000"
                                    aria-label={`Milestone ${index + 1} amount`}
                                    onChange={(event) =>
                                        update(index, {
                                            amount: event.target.value
                                                ? Number(event.target.value)
                                                : null,
                                        })
                                    }
                                />
                                <button
                                    type="button"
                                    aria-label={`Remove milestone ${index + 1}`}
                                    className="cursor-pointer text-muted-foreground opacity-60 transition-colors hover:text-destructive hover:opacity-100"
                                    onClick={() =>
                                        onChange(
                                            value.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <TrashIcon />
                                </button>
                            </div>

                            {errors[`milestones.${index}.title`] && (
                                <p className="text-[11.5px] text-red-600 dark:text-red-400">
                                    {errors[`milestones.${index}.title`]}
                                </p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
