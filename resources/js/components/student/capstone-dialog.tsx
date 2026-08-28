import { useEffect, useState } from 'react';

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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** What the board is currently ranking against, so reopening is not a blank slate. */
    title: string;
    description: string;
    onConfirm: (capstone: { title: string; description: string }) => void;
};

/**
 * "Your capstone project" — the advanced search behind the briefs box.
 *
 * A skill list says what a student already knows; it does not say what they
 * are building this term, and that is what a client's brief has to line up
 * with. So the board can rank against two sentences the student writes here
 * instead of against their saved profile.
 *
 * Not a Form: there is nothing to post. Confirming re-visits the board with
 * the capstone in the query string, which keeps the search shareable, back-
 * navigable and reloadable — a POST would lose all three.
 */
export default function CapstoneDialog({
    open,
    onOpenChange,
    title,
    description,
    onConfirm,
}: Props) {
    const [draft, setDraft] = useState({ title, description });

    /*
     * The board owns the capstone; this is a draft of it. Re-seeding on open
     * means cancelling genuinely discards, rather than leaving half an edit
     * behind for the next time the box is clicked.
     */
    useEffect(() => {
        if (open) {
            setDraft({ title, description });
        }
    }, [open, title, description]);

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
                    <DialogTitle>Your capstone project</DialogTitle>
                    <DialogDescription>
                        We match open briefs against what you are building this
                        term.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <label htmlFor="capstone-title" className="text-[13px]">
                            Capstone title
                        </label>
                        <Input
                            id="capstone-title"
                            value={draft.title}
                            placeholder="Ex: Inventory System with Predictive Analytics"
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
                            htmlFor="capstone-description"
                            className="text-[13px]"
                        >
                            Brief description
                        </label>
                        <Textarea
                            id="capstone-description"
                            rows={5}
                            value={draft.description}
                            placeholder="What does the system do, who uses it, and what stage is it in?"
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
