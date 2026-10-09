import { Head, Link } from '@inertiajs/react';
import { ArrowLeftIcon, PrinterIcon, WarningIcon } from '@phosphor-icons/react';

import { AddendumDocument } from '@/components/agreements/addendum-document';
import { Btn } from '@/components/sdpc/btn';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { show as addendumShow } from '@/routes/agreements/addenda';
import type { Addendum } from '@/types/agreements';

/**
 * The addendum filled in, ready to print or save as a PDF — signed or not.
 *
 * The same document the addendum screen edits, drawn from the same payload,
 * with nothing but the page: no app chrome (app.tsx gives it no layout), no
 * controls. An unsigned copy prints with the signature lines blank; a signed
 * one with each party's name and date. GCash accounts print masked.
 */
export default function AddendumPrintable({
    addendum,
}: {
    addendum: Addendum;
}) {
    const team = useCurrentTeam();
    const blocked = addendum.viewer.signingBlockedBy;
    const isSigned =
        addendum.status === 'active' || addendum.status === 'completed';

    return (
        <>
            <Head title={`${addendum.reference} Addendum`} />

            <div
                style={{
                    minHeight: '100vh',
                    background: '#ecebe7',
                    padding: '0 clamp(8px, 3vw, 24px) 48px',
                }}
            >
                <div
                    className="no-print"
                    style={{
                        position: 'sticky',
                        top: 0,
                        zIndex: 1,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        flexWrap: 'wrap',
                        maxWidth: '210mm',
                        margin: '0 auto',
                        padding: '14px 0',
                        background: '#ecebe7',
                    }}
                >
                    <Btn asChild variant="ghost">
                        <Link
                            href={addendumShow.url({
                                current_team: team.slug,
                                agreement: addendum.agreementId,
                                addendum: addendum.id,
                            })}
                        >
                            <ArrowLeftIcon />
                            Back to the addendum
                        </Link>
                    </Btn>
                    <span
                        style={{
                            marginRight: 'auto',
                            fontSize: 12.5,
                            color: '#555',
                        }}
                    >
                        {addendum.reference} · {addendum.statusLabel} ·{' '}
                        {isSigned ? 'signed copy' : 'unsigned copy'}
                    </span>
                    <Btn variant="primary" onClick={() => window.print()}>
                        <PrinterIcon />
                        Print or save as PDF
                    </Btn>
                </div>

                {blocked && (
                    <div
                        className="no-print"
                        role="status"
                        style={{
                            display: 'flex',
                            gap: 8,
                            alignItems: 'flex-start',
                            maxWidth: '210mm',
                            margin: '0 auto 12px',
                            padding: '10px 14px',
                            borderRadius: 8,
                            background: '#fff4e5',
                            color: '#7a4a00',
                            fontSize: 13,
                        }}
                    >
                        <WarningIcon style={{ flex: 'none', marginTop: 2 }} />
                        <span>Not ready to sign yet: {blocked}</span>
                    </div>
                )}

                <AddendumDocument addendum={addendum} />
            </div>
        </>
    );
}
