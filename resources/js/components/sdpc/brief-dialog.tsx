import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Input, Textarea } from '@/components/sdpc/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type Brief = { title: string; description: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Heading, sub-line and the two field labels — the only things that differ. */
    heading: string;
    description: string;
    titleLabel: string;
    titlePlaceholder: string;
    bodyLabel: string;
    bodyPlaceholder: string;
    /** What is currently being ranked against, so reopening is not a blank slate. */
    value: Brief;
    onConfirm: (brief: Brief) => void;
};

/**
 * The advanced search behind both boards, asked from whichever side you are on.
 *
 * A student describes the capstone they are building; a client describes the
 * system they want built. It is the same question — what is this work? — and
 * the same two fields, so it is one component with the wording passed in
 * rather than two that drift apart.
 *
 * This exists because the skill-tag filters were removed. Clients come from
 * every trade and mostly cannot name a stack, and asking them to produced tag
 * lists that were guesses. Their own sentences are the better input, and they
 * are what the matcher reads.
 *
 * Not a Form: there is nothing to post. Confirming re-visits the board with
 * the brief in the query string, so the ranked result stays shareable,
 * reloadable and reachable with the back button.
 */
export default function BriefDialog({
    open,
    onOpenChange,
    heading,
    description,
    titleLabel,
    titlePlaceholder,
    bodyLabel,
    bodyPlaceholder,
    value,
    onConfirm,
}: Props) {
    const [draft, setDraft] = useState<Brief>(value);
    const [wasOpen, setWasOpen] = useState(open);

    /*
     * The board owns the brief; this is a draft of it. Re-seeding as it opens
     * means Cancel genuinely discards, rather than leaving half an edit behind
     * for the next time the box is clicked.
     *
     * Adjusted during render rather than in an effect. React's own guidance
     * for "reset state when a prop changes": an effect would paint the stale
     * draft for one frame first, and re-render immediately afterwards.
     */
    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setDraft(value);
        }
    }

    const submit = () => {
        onConfirm({
            title: draft.title.trim(),
            description: draft.description.trim(),
        });
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle>{heading}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <label htmlFor="brief-title" className="text-[13px]">
                            {titleLabel}
                        </label>
                        <Input
                            id="brief-title"
                            value={draft.title}
                            placeholder={titlePlaceholder}
                            onChange={(event) =>
                                setDraft((current) => ({
                                    ...current,
                                    title: event.target.value,
                                }))
                            }
                            /*
                             * Enter confirms from the single-line field, the
                             * way it would in any search box. The textarea
                             * below deliberately does not — a description
                             * wants paragraphs.
                             */
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    submit();
                                }
                            }}
                        />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="brief-description"
                            className="text-[13px]"
                        >
                            {bodyLabel}
                        </label>
                        <Textarea
                            id="brief-description"
                            rows={5}
                            value={draft.description}
                            placeholder={bodyPlaceholder}
                            onChange={(event) =>
                                setDraft((current) => ({
                                    ...current,
                                    description: event.target.value,
                                }))
                            }
                        />
                    </div>
                </div>

                {/* A direct child of the dialog, so gap-4 already spaces it —
                    gap-3 only. See .ai/rules/components.md. */}
                <DialogFooter className="gap-3">
                    <Btn variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Btn>
                    <Btn variant="primary" onClick={submit}>
                        Confirm
                    </Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
