import { Head, Link } from '@inertiajs/react';
import { ArrowLeftIcon, PrinterIcon, WarningIcon } from '@phosphor-icons/react';

import { MemorandumDocument } from '@/components/agreements/memorandum-document';
import { Btn } from '@/components/sdpc/btn';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { contract as agreementContract } from '@/routes/agreements';
import type { Agreement } from '@/types/agreements';

type Props = {
    agreement: Agreement;
};

/**
 * The finished Memorandum of Agreement, ready to print and sign by hand.
 *
 * The same document the contract screen edits, drawn from the same payload,
 * with nothing but the page: no app chrome (app.tsx gives it no layout), no
 * controls, no prompts. "Print" hands it to the browser, which also saves it
 * as a PDF. The blank template is the download on the contract screen.
 */
export default function AgreementPrintable({ agreement }: Props) {
    const team = useCurrentTeam();
    const moa = agreement.moa;
    const blocked = agreement.viewer.signingBlockedBy;

    return (
        <>
            <Head title={`${agreement.reference} Memorandum of Agreement`} />

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
                            href={agreementContract.url({
                                current_team: team.slug,
                                agreement: agreement.id,
                            })}
                        >
                            <ArrowLeftIcon />
                            Back to the memorandum
                        </Link>
                    </Btn>
                    <span
                        style={{
                            marginRight: 'auto',
                            fontSize: 12.5,
                            color: '#555',
                        }}
                    >
                        {agreement.reference} · v{agreement.version} ·{' '}
                        {agreement.statusLabel}
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

                {moa ? (
                    <MemorandumDocument
                        moa={moa}
                        reference={`${agreement.reference} · v${agreement.version}`}
                    />
                ) : null}
            </div>
        </>
    );
}
