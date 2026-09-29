import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type AgreementDocument = {
    slug: string;
    title: string;
    intro: string;
    sections: { heading: string; body: string }[];
};

type Props = {
    documents: AgreementDocument[];
    open: boolean;
    onAccept: () => void;
    onReject: () => void;
};

/** How close to the end counts as "read to the bottom", for sub-pixel scroll. */
const BOTTOM_SLACK = 4;

/**
 * The Terms of Service, User Agreement and Privacy Policy, read before the
 * sign-up box can be ticked.
 *
 * Accept stays disabled until the text has been scrolled to the end. Reject,
 * Escape and clicking outside all leave the box unticked. Radix's Dialog keeps
 * keyboard focus inside while it is open.
 */
export default function AgreementDialog({
    documents,
    open,
    onAccept,
    onReject,
}: Props) {
    const [reachedBottom, setReachedBottom] = useState(false);

    /* Every opening is a fresh read, so closing forgets the scroll. */
    const accept = () => {
        setReachedBottom(false);
        onAccept();
    };

    const reject = () => {
        setReachedBottom(false);
        onReject();
    };

    const check = useCallback((box: HTMLDivElement | null) => {
        if (
            box !== null &&
            box.scrollTop + box.clientHeight >= box.scrollHeight - BOTTOM_SLACK
        ) {
            setReachedBottom(true);
        }
    }, []);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    reject();
                }
            }}
        >
            <DialogContent
                className="sm:max-w-2xl"
                data-test="agreement-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Terms and Policies</DialogTitle>
                    <DialogDescription>
                        Read the Terms of Service, User Agreement and Privacy
                        Policy to the end before you accept.
                    </DialogDescription>
                </DialogHeader>

                <div
                    /* A text short enough not to scroll is already read. */
                    ref={check}
                    onScroll={(event) => check(event.currentTarget)}
                    className="max-h-[55vh] space-y-6 overflow-y-auto rounded-md border p-4 text-sm leading-relaxed"
                    data-test="agreement-text"
                >
                    {documents.map((document) => (
                        <section key={document.slug} className="space-y-3">
                            <h3 className="text-base font-semibold">
                                {document.title}
                            </h3>
                            <p className="text-muted-foreground">
                                {document.intro}
                            </p>
                            {document.sections.map((section, index) => (
                                <div key={section.heading}>
                                    <h4 className="font-medium">
                                        {index + 1}. {section.heading}
                                    </h4>
                                    <p className="text-muted-foreground">
                                        {section.body}
                                    </p>
                                </div>
                            ))}
                        </section>
                    ))}
                </div>

                <DialogFooter className="items-center gap-3">
                    {!reachedBottom && (
                        <span
                            className="mr-auto text-xs text-muted-foreground"
                            data-test="agreement-scroll-hint"
                        >
                            Please scroll to the bottom to accept.
                        </span>
                    )}

                    <Button variant="secondary" onClick={reject}>
                        Reject
                    </Button>
                    <Button
                        data-test="agreement-accept"
                        disabled={!reachedBottom}
                        onClick={accept}
                    >
                        Accept
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
