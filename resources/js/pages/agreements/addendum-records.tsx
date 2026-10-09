import { Head, Link } from '@inertiajs/react';
import { ArrowLeftIcon, PrinterIcon } from '@phosphor-icons/react';

import { Btn } from '@/components/sdpc/btn';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { show as addendumShow } from '@/routes/agreements/addenda';
import type { Addendum } from '@/types/agreements';

/**
 * The transaction record of one addendum (Section V.3's audit trail), to
 * print or save as a PDF.
 *
 * Both parties can open it. Each milestone carries SDPC's invoice number and,
 * once paid, PayMongo's payment id (pay_…), the method and when it cleared.
 * The parties' GCash accounts appear masked, as everywhere else.
 */
export default function AddendumRecords({
    addendum,
    generatedAt,
}: {
    addendum: Addendum;
    generatedAt: string;
}) {
    const team = useCurrentTeam();
    const doc = addendum.document;
    const paid = addendum.payments
        .filter((payment) => payment.status === 'paid')
        .reduce((sum, payment) => sum + payment.amount, 0);
    const total = addendum.payments.reduce(
        (sum, payment) => sum + payment.amount,
        0,
    );
    const peso = (centavos: number) =>
        `₱${(centavos / 100).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;

    return (
        <>
            <Head title={`${addendum.reference} Transaction records`} />

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
                    <span style={{ marginRight: 'auto' }} />
                    <Btn variant="primary" onClick={() => window.print()}>
                        <PrinterIcon />
                        Print or save as PDF
                    </Btn>
                </div>

                <article className="moa-doc addendum-doc">
                    <header className="moa-cover">
                        <div className="moa-cover-title">
                            TRANSACTION RECORDS
                        </div>
                        <div className="moa-cover-joint">for</div>
                        <div className="moa-cover-name">{doc.services}</div>
                        <div className="moa-reference">
                            {addendum.reference} · Addendum to contract{' '}
                            {addendum.agreementReference} · generated{' '}
                            {generatedAt}
                        </div>
                    </header>

                    <table
                        className="addendum-table"
                        style={{ marginBottom: 18 }}
                    >
                        <tbody>
                            <tr>
                                <th scope="row">CA1 · Student Team Lead</th>
                                <td>{doc.ca1}</td>
                                <td>
                                    GCash{' '}
                                    {doc.gcash.student ?? 'not registered'}
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">CA2 · Client Representative</th>
                                <td>{doc.ca2}</td>
                                <td>
                                    GCash {doc.gcash.client ?? 'not registered'}
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Addendum status</th>
                                <td colSpan={2}>
                                    {addendum.statusLabel}
                                    {addendum.executedOn
                                        ? ` · signed ${addendum.executedOn}`
                                        : ''}
                                    {addendum.completedOn
                                        ? ` · fully paid ${addendum.completedOn}`
                                        : ''}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    {addendum.payments.length === 0 ? (
                        <p className="moa-paragraph" style={{ marginLeft: 0 }}>
                            No transactions yet. The two milestones are written
                            when both parties have signed the addendum.
                        </p>
                    ) : (
                        <table className="addendum-table">
                            <thead>
                                <tr>
                                    <th scope="col">Invoice</th>
                                    <th scope="col">Milestone</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">PayMongo Transaction ID</th>
                                    <th scope="col">Method</th>
                                    <th scope="col">Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                {addendum.payments.map((payment) => (
                                    <tr key={payment.id}>
                                        <td>{payment.invoiceNumber}</td>
                                        <td>
                                            {payment.label} (
                                            {payment.percentage}%)
                                        </td>
                                        <td>{payment.amountLabel}</td>
                                        <td>{payment.statusLabel}</td>
                                        <td>
                                            {payment.providerPaymentId ?? '—'}
                                            {payment.gateway === 'simulated'
                                                ? ' (test)'
                                                : ''}
                                        </td>
                                        <td>{payment.method ?? '—'}</td>
                                        <td>
                                            {payment.paidAt ?? '—'}
                                            {payment.paidBy
                                                ? ` · ${payment.paidBy}`
                                                : ''}
                                        </td>
                                    </tr>
                                ))}
                                <tr>
                                    <th scope="row" colSpan={2}>
                                        TOTAL
                                    </th>
                                    <td>{peso(total)}</td>
                                    <td colSpan={4}>
                                        Paid {peso(paid)} · outstanding{' '}
                                        {peso(total - paid)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    )}

                    <p
                        className="moa-reference"
                        style={{ marginTop: 18, textAlign: 'left' }}
                    >
                        Payments are cleared by PayMongo (supporting GCash).
                        SDPC records them and is not a financial institution or
                        escrow (Addendum, Section VII.2).
                    </p>
                </article>
            </div>
        </>
    );
}
