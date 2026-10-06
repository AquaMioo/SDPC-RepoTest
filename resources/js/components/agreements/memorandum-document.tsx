import {
    PencilSimpleIcon,
    PlusIcon,
    TrashSimpleIcon,
} from '@phosphor-icons/react';
import type { ReactNode } from 'react';
import { Fragment, useState } from 'react';

import {
    MemorandumEntryDialog,
    RemoveEntryDialog,
} from '@/components/agreements/requirement-dialogs';
import { Btn } from '@/components/sdpc/btn';
import type { Moa, MoaEntry, MoaSection } from '@/types/agreements';

/** Secondary text on the paper (its ink and paper live in app.css: .moa-doc). */
const FAINT = '#6b6b6b';

type Editing = {
    teamSlug: string;
    agreementId: number;
};

type Dialog =
    | { kind: 'add'; section: MoaSection }
    | { kind: 'edit'; section: MoaSection; entry: MoaEntry }
    | { kind: 'remove'; section: MoaSection; entry: MoaEntry };

/**
 * The SDPC Memorandum of Agreement as a document: sections I to X in the
 * template's order, on A4 paper in Times New Roman, with every blank filled
 * and every party's additions in place.
 *
 * One component draws it for the contract screen and for the printable copy,
 * so what is added on screen is exactly what prints. With `editing`, each
 * open section offers "Add requirement" (Section VII: "Add service") and each
 * reader's own additions can be edited or removed; those controls and the
 * template's "add more here" prompts carry `no-print`, and an optional section
 * nobody added to prints with no trace of its placeholder.
 */
export function MemorandumDocument({
    moa,
    editing = null,
    reference,
}: {
    moa: Moa;
    /** Pass to offer the add, edit and remove controls. */
    editing?: Editing | null;
    /** "SDPC-2026-001 · v1", printed small under the title. */
    reference?: string;
}) {
    const [dialog, setDialog] = useState<Dialog | null>(null);

    return (
        <article className="moa-doc">
            <header className="moa-cover">
                <div className="moa-cover-title">{moa.title}</div>
                <div className="moa-cover-joint">between</div>
                <div className="moa-cover-name">{moa.ca1}</div>
                <div className="moa-cover-joint">and</div>
                <div className="moa-cover-name">{moa.ca2}</div>
                <div className="moa-cover-joint">for</div>
                <div className="moa-cover-name">{moa.services}</div>
                {reference && <div className="moa-reference">{reference}</div>}
            </header>

            {moa.sections.map((section) => (
                <section
                    key={section.numeral}
                    className={
                        section.newPage
                            ? 'moa-section moa-new-page'
                            : 'moa-section'
                    }
                    aria-labelledby={`moa-${section.numeral}`}
                >
                    <h2 id={`moa-${section.numeral}`} className="moa-heading">
                        <span className="moa-numeral">{section.numeral}.</span>
                        {section.heading}
                    </h2>

                    <SectionBody
                        section={section}
                        editing={editing}
                        onEdit={(entry) =>
                            setDialog({ kind: 'edit', section, entry })
                        }
                        onRemove={(entry) =>
                            setDialog({ kind: 'remove', section, entry })
                        }
                    />

                    {editing && section.addition && (
                        <Prompt
                            section={section}
                            onAdd={() => setDialog({ kind: 'add', section })}
                        />
                    )}

                    {section.numeral === 'X' && <SignatureBlock moa={moa} />}
                </section>
            ))}

            {editing && dialog?.kind === 'add' && (
                <MemorandumEntryDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    teamSlug={editing.teamSlug}
                    agreementId={editing.agreementId}
                    section={dialog.section}
                    entry={null}
                />
            )}
            {editing && dialog?.kind === 'edit' && (
                <MemorandumEntryDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    teamSlug={editing.teamSlug}
                    agreementId={editing.agreementId}
                    section={dialog.section}
                    entry={dialog.entry}
                />
            )}
            {editing && dialog?.kind === 'remove' && (
                <RemoveEntryDialog
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                    teamSlug={editing.teamSlug}
                    agreementId={editing.agreementId}
                    section={dialog.section}
                    entry={dialog.entry}
                />
            )}
        </article>
    );
}

/**
 * The section's own wording, then what the parties added to it.
 *
 * A numbered addition continues the section's last numbered list (IV picks up
 * at 6), a paragraph addition follows the wording as its own paragraph, and a
 * Section VII service is numbered with its Objective and Scope.
 */
function SectionBody({
    section,
    editing,
    onEdit,
    onRemove,
}: {
    section: MoaSection;
    editing: Editing | null;
    onEdit: (entry: MoaEntry) => void;
    onRemove: (entry: MoaEntry) => void;
}) {
    const as = section.addition?.as;
    const lastNumbered = section.blocks.reduce(
        (last, block, index) => (block.type === 'numbered' ? index : last),
        -1,
    );

    const controls = (entry: MoaEntry) =>
        editing ? (
            <EntryControls
                entry={entry}
                onEdit={() => onEdit(entry)}
                onRemove={() => onRemove(entry)}
            />
        ) : null;

    return (
        <>
            {section.blocks.map((block, index) => {
                if (block.type === 'paragraph') {
                    return (
                        <p key={index} className="moa-paragraph">
                            <Bold text={block.text} />
                        </p>
                    );
                }

                if (block.type === 'lettered') {
                    return (
                        <ol key={index} className="moa-list" type="a">
                            {block.items.map((item) => (
                                <li key={item}>
                                    <Bold text={item} />
                                </li>
                            ))}
                        </ol>
                    );
                }

                /* Additions continue the section's numbering: IV picks up at 6. */
                const appended =
                    as === 'numbered' && index === lastNumbered
                        ? section.entries
                        : [];

                return (
                    <ol key={index} className="moa-list" type="1">
                        {block.items.map((item) => (
                            <li key={item.text}>
                                <Bold text={item.text} />
                                {item.lettered.length > 0 && (
                                    <ol className="moa-sublist" type="a">
                                        {item.lettered.map((letter) => (
                                            <li key={letter}>
                                                <Bold text={letter} />
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </li>
                        ))}
                        {appended.map((entry) => (
                            <li key={`entry-${entry.id}`}>
                                <span className="moa-added">{entry.body}</span>
                                {controls(entry)}
                            </li>
                        ))}
                    </ol>
                );
            })}

            {as === 'paragraph' &&
                section.entries.map((entry) => (
                    <p key={entry.id} className="moa-paragraph">
                        <span className="moa-added">{entry.body}</span>
                        {controls(entry)}
                    </p>
                ))}

            {/*
             * Section VII: each service numbered, its Objective in bold and its
             * Scope straight after it in plain text — no labels.
             */}
            {as === 'services' && section.entries.length > 0 && (
                <ol className="moa-list moa-services" type="1">
                    {section.entries.map((entry) => (
                        <li key={entry.id}>
                            <strong>{entry.title}</strong>
                            {': '}
                            <span className="moa-added">{entry.body}</span>
                            {controls(entry)}
                        </li>
                    ))}
                </ol>
            )}
        </>
    );
}

/**
 * The template's "add more here" line, as a prompt to the people editing:
 * never printed. Section VII also shows the template's sample wording and
 * says it is required.
 */
function Prompt({
    section,
    onAdd,
}: {
    section: MoaSection;
    onAdd: () => void;
}) {
    const addition = section.addition;

    if (addition === null) {
        return null;
    }

    const isEmpty = section.entries.length === 0;

    return (
        <div className="moa-prompt no-print">
            {isEmpty && (
                <>
                    <div style={{ fontStyle: 'italic' }}>
                        {addition.placeholder}
                    </div>
                    {addition.example.map((paragraph) => (
                        <p
                            key={paragraph}
                            style={{ margin: '6px 0 0', fontStyle: 'italic' }}
                        >
                            {paragraph}
                        </p>
                    ))}
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
                    variant={
                        addition.isRequired && isEmpty ? 'primary' : 'secondary'
                    }
                    onClick={onAdd}
                >
                    <PlusIcon />
                    {section.key === 'services'
                        ? 'Add service'
                        : 'Add requirement'}
                </Btn>
                <span
                    style={{
                        fontFamily: 'var(--font-sans, sans-serif)',
                        fontSize: 11.5,
                        color: addition.isRequired && isEmpty ? '#a33' : FAINT,
                    }}
                >
                    {addition.isRequired
                        ? isEmpty
                            ? 'Required: neither side can sign until Section VII describes at least one service.'
                            : 'Required. When the work starts, each service becomes one item in Project Management’s Objective & Scope: the objective as its title, the scope as its description.'
                        : 'Optional. Left out of the printed copy when nothing is added.'}
                </span>
            </div>
        </div>
    );
}

/** Who added a line, and, for them, the way to change or remove it. */
function EntryControls({
    entry,
    onEdit,
    onRemove,
}: {
    entry: MoaEntry;
    onEdit: () => void;
    onRemove: () => void;
}) {
    return (
        <span className="moa-entry-controls no-print">
            {entry.authorName && (
                <span>
                    Added by {entry.authorName}
                    {entry.authorSide ? ` (${entry.authorSide})` : ''}
                </span>
            )}
            {entry.canChange && (
                <>
                    <Btn variant="ghost" onClick={onEdit}>
                        <PencilSimpleIcon />
                        Edit
                    </Btn>
                    <Btn variant="ghost" onClick={onRemove}>
                        <TrashSimpleIcon />
                        Remove
                    </Btn>
                </>
            )}
        </span>
    );
}

/**
 * Section X's signature lines, as the template draws them: a line to sign on
 * by hand, then the printed name, title and date, each labelled underneath.
 */
function SignatureBlock({ moa }: { moa: Moa }) {
    const columns = [
        ['CA1', moa.signatories.client],
        ['CA2', moa.signatories.student],
    ] as const;

    return (
        <div className="moa-signatures">
            {columns.map(([label, signatory]) => (
                <div key={label}>
                    <div className="moa-signature-party">{label}</div>
                    <SignLine label="(Signature)" value={null} />
                    <SignLine
                        label="(Print Name)"
                        value={signatory.printName}
                    />
                    <SignLine label="(Title)" value={signatory.title} />
                    <SignLine label="(Date)" value={signatory.signedOn} />
                </div>
            ))}
        </div>
    );
}

function SignLine({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="moa-sign-line">
            <div className="moa-sign-value">{value ?? ' '}</div>
            <div className="moa-sign-label">{label}</div>
        </div>
    );
}

/**
 * Text with the **bold** runs the template prints — the filled-in blanks and
 * the clause labels. Only that marker is read; nothing else is markup.
 */
function Bold({ text }: { text: string }): ReactNode {
    return (
        <>
            {text
                .split(/\*\*(.+?)\*\*/)
                .map((part, index) =>
                    index % 2 === 1 ? (
                        <strong key={index}>{part}</strong>
                    ) : (
                        <Fragment key={index}>{part}</Fragment>
                    ),
                )}
        </>
    );
}
