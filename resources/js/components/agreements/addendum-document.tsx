import {
    CurrencyCircleDollarIcon,
    PencilSimpleIcon,
    PlusIcon,
    TrashSimpleIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';

import {
    AddendumAmountDialog,
    AddendumServiceDialog,
    RemoveAddendumServiceDialog,
} from '@/components/agreements/addendum-dialogs';
import type { AddendumTarget } from '@/components/agreements/addendum-dialogs';
import { Bold } from '@/components/agreements/memorandum-document';
import { Btn } from '@/components/sdpc/btn';
import type {
    Addendum,
    AddendumEntry,
    AddendumSection,
    AddendumSignatory,
} from '@/types/agreements';

/** Secondary text on the paper (its ink and paper live in app.css: .moa-doc). */
const FAINT = '#6b6b6b';

type Dialog =
    | { kind: 'add' }
    | { kind: 'edit'; entry: AddendumEntry }
    | { kind: 'remove'; entry: AddendumEntry }
    | { kind: 'amount' };

/**
 * The Payment & Project Extension Addendum as a document: SDPC_Addendum.pdf's
 * sections I to IX on A4 paper in the memorandum's type, every blank filled.
 *
 * One component draws it for the addendum screen and for the printable copy,
 * so what is added on screen is exactly what prints — the rule the memorandum
 * follows. With `editing`, Section II offers "Add service" and each reader's
 * own services can be edited or removed, and Section IV offers "Set target
 * amount"; those controls and Section II's sample wording carry `no-print`.
 * GCash accounts arrive masked and are printed masked.
 */
export function AddendumDocument({
    addendum,
    editing = null,
}: {
    addendum: Addendum;
    /** Pass to offer the Section II and Section IV controls. */
    editing?: AddendumTarget | null;
}) {
    const [dialog, setDialog] = useState<Dialog | null>(null);
    const doc = addendum.document;

    return (
        <article className="moa-doc addendum-doc">
            <header className="moa-cover">
                <div className="moa-cover-title">{doc.title}</div>
                <div className="moa-cover-joint">between</div>
                <div className="moa-cover-name">{doc.ca1}</div>
                <div className="addendum-cover-label">{doc.cover.ca1}</div>
                <div className="moa-cover-joint">and</div>
                <div className="moa-cover-name">{doc.ca2}</div>
                <div className="addendum-cover-label">{doc.cover.ca2}</div>
                <div className="moa-cover-joint">for</div>
                <div className="moa-cover-name">{doc.services}</div>
                <div className="addendum-cover-label">{doc.cover.services}</div>
                <div className="moa-reference">
                    {addendum.reference} · Addendum to contract{' '}
                    {addendum.agreementReference}
                </div>
            </header>

            {doc.sections.map((section) => (
                <section
                    key={section.numeral}
                    className={
                        section.newPage
                            ? 'moa-section moa-new-page'
                            : 'moa-section'
                    }
                    aria-labelledby={`addendum-${section.numeral}`}
                >
                    <h2
                        id={`addendum-${section.numeral}`}
                        className="moa-heading"
                    >
                        <span className="moa-numeral">{section.numeral}.</span>
                        {section.heading}
                    </h2>

                    <Blocks section={section} />

                    {section.numeral === 'I' && (
                        <p className="moa-paragraph">
                            GCash accounts on record: <strong>CA1</strong>{' '}
                            {doc.gcash.student ?? 'not registered'};{' '}
                            <strong>CA2</strong>{' '}
                            {doc.gcash.client ?? 'not registered'}.
                        </p>
                    )}

                    {section.key === 'services' && (
                        <Services
                            entries={doc.entries}
                            example={doc.example}
                            editing={editing}
                            onAdd={() => setDialog({ kind: 'add' })}
                            onEdit={(entry) =>
                                setDialog({ kind: 'edit', entry })
                            }
                            onRemove={(entry) =>
                                setDialog({ kind: 'remove', entry })
                            }
                        />
                    )}

                    {section.key === 'payments' && (
                        <>
                            <MilestoneTable section={section} />
                            {editing && (
                                <div className="moa-prompt no-print">
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                            flexWrap: 'wrap',
                                        }}
                                    >
                                        <Btn
                                            variant={
                                                addendum.totalAmount === null
                                                    ? 'primary'
                                                    : 'secondary'
                                            }
                                            onClick={() =>
                                                setDialog({ kind: 'amount' })
                                            }
                                        >
                                            <CurrencyCircleDollarIcon />
                                            {addendum.totalAmount === null
                                                ? 'Set target amount'
                                                : 'Change target amount'}
                                        </Btn>
                                        <span
                                            style={{
                                                fontFamily:
                                                    'var(--font-sans, sans-serif)',
                                                fontSize: 11.5,
                                                color:
                                                    addendum.totalAmount ===
                                                    null
                                                        ? '#a33'
                                                        : FAINT,
                                            }}
                                        >
                                            Only the target amount can be set
                                            here, up to ₱
                                            {addendum.limits.max.toLocaleString()}
                                            . The milestones are cut from it.
                                        </span>
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {section.numeral === 'IX' && (
                        <SignatureBlock
                            student={doc.signatories.student}
                            client={doc.signatories.client}
                        />
                    )}
                </section>
            ))}

            {editing && dialog?.kind === 'add' && (
                <AddendumServiceDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    target={editing}
                    entry={null}
                />
            )}
            {editing && dialog?.kind === 'edit' && (
                <AddendumServiceDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    target={editing}
                    entry={dialog.entry}
                />
            )}
            {editing && dialog?.kind === 'remove' && (
                <RemoveAddendumServiceDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    target={editing}
                    entry={dialog.entry}
                />
            )}
            {editing && dialog?.kind === 'amount' && (
                <AddendumAmountDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    target={editing}
                    current={addendum.totalAmount}
                    limits={addendum.limits}
                />
            )}
        </article>
    );
}

/** A section's fixed wording: paragraphs, and numbered lists. */
function Blocks({ section }: { section: AddendumSection }) {
    return (
        <>
            {section.blocks.map((block, index) =>
                block.type === 'paragraph' ? (
                    <p key={index} className="moa-paragraph">
                        <Bold text={block.text} />
                    </p>
                ) : (
                    <ol key={index} className="moa-list" type="1">
                        {block.items.map((item) => (
                            <li key={item}>
                                <Bold text={item} />
                            </li>
                        ))}
                    </ol>
                ),
            )}
        </>
    );
}

/**
 * Section II's services, numbered "**Objective**: scope" like the
 * memorandum's Section VII, and for the parties the way to add their own.
 */
function Services({
    entries,
    example,
    editing,
    onAdd,
    onEdit,
    onRemove,
}: {
    entries: AddendumEntry[];
    example: string[];
    editing: AddendumTarget | null;
    onAdd: () => void;
    onEdit: (entry: AddendumEntry) => void;
    onRemove: (entry: AddendumEntry) => void;
}) {
    const isEmpty = entries.length === 0;

    return (
        <>
            {!isEmpty && (
                <ol className="moa-list moa-services" type="1">
                    {entries.map((entry) => (
                        <li key={entry.id}>
                            <strong>{entry.objective}</strong>
                            {': '}
                            <span className="moa-added">{entry.scope}</span>
                            {editing && (
                                <span className="moa-entry-controls no-print">
                                    {entry.authorName && (
                                        <span>
                                            Added by {entry.authorName}
                                            {entry.authorSide
                                                ? ` (${entry.authorSide})`
                                                : ''}
                                        </span>
                                    )}
                                    {entry.canChange && (
                                        <>
                                            <Btn
                                                variant="ghost"
                                                onClick={() => onEdit(entry)}
                                            >
                                                <PencilSimpleIcon />
                                                Edit
                                            </Btn>
                                            <Btn
                                                variant="ghost"
                                                onClick={() => onRemove(entry)}
                                            >
                                                <TrashSimpleIcon />
                                                Remove
                                            </Btn>
                                        </>
                                    )}
                                </span>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            {editing && (
                <div className="moa-prompt no-print">
                    {isEmpty && (
                        <>
                            <div style={{ fontStyle: 'italic' }}>
                                List the extended services as an objective and
                                its scope, for example:
                            </div>
                            <ol
                                className="moa-list"
                                type="1"
                                style={{
                                    margin: '6px 0 0',
                                    fontStyle: 'italic',
                                }}
                            >
                                {example.map((line) => (
                                    <li key={line}>
                                        <Bold text={line} />
                                    </li>
                                ))}
                            </ol>
                        </>
                    )}
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            flexWrap: 'wrap',
                            marginTop: isEmpty ? 10 : 0,
                        }}
                    >
                        <Btn
                            variant={isEmpty ? 'primary' : 'secondary'}
                            onClick={onAdd}
                        >
                            <PlusIcon />
                            Add service
                        </Btn>
                        <span
                            style={{
                                fontFamily: 'var(--font-sans, sans-serif)',
                                fontSize: 11.5,
                                color: isEmpty ? '#a33' : FAINT,
                            }}
                        >
                            {isEmpty
                                ? 'Required: neither side can sign until Section II describes at least one service.'
                                : 'Required. Once the down payment clears, each service becomes one task in Project Management’s Objective & Scope.'}
                        </span>
                    </div>
                </div>
            )}
        </>
    );
}

/** Section IV's table: each milestone's share, amount and condition, and the total. */
function MilestoneTable({ section }: { section: AddendumSection }) {
    return (
        <div className="addendum-table-wrap">
            <table className="addendum-table">
                <thead>
                    <tr>
                        <th scope="col">Milestone</th>
                        <th scope="col">Description</th>
                        <th scope="col">Allocation</th>
                        <th scope="col">Target Amount</th>
                        <th scope="col">Trigger &amp; Release Condition</th>
                    </tr>
                </thead>
                <tbody>
                    {section.milestones.map((row) => (
                        <tr key={row.name}>
                            <th scope="row">{row.name}</th>
                            <td>{row.description}</td>
                            <td>{row.allocation}</td>
                            <td>{row.amount ?? '—'}</td>
                            <td>{row.condition}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Section IX's signature lines as the form draws them: each agency, its
 * representative's title, the name over the signature line and the date.
 */
function SignatureBlock({
    student,
    client,
}: {
    student: AddendumSignatory;
    client: AddendumSignatory;
}) {
    return (
        <div className="moa-signatures">
            {[student, client].map((signatory) => (
                <div key={signatory.party}>
                    <div className="moa-signature-party">
                        {signatory.party}
                        <div style={{ fontWeight: 400 }}>{signatory.title}</div>
                    </div>
                    <div className="moa-sign-line">
                        <div className="moa-sign-value">
                            {signatory.printName}
                            {signatory.signedOn
                                ? ' (signed electronically)'
                                : ''}
                        </div>
                        <div className="moa-sign-label">
                            (Signature over Printed Name)
                        </div>
                    </div>
                    <div className="moa-sign-line">
                        <div className="moa-sign-value">
                            Title: {signatory.title}
                        </div>
                    </div>
                    <div className="moa-sign-line">
                        <div className="moa-sign-value">
                            Date: {signatory.signedOn ?? ''}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
