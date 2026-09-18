import { Head, Link } from '@inertiajs/react';

import { Btn } from '@/components/sdpc/btn';
import PublicLayout from '@/layouts/public-layout';
import { home, legal } from '@/routes';

type Section = { heading: string; body: string };

type DocumentLink = { slug: string; title: string };

type Props = {
    document: {
        slug: string;
        title: string;
        intro: string;
        sections: Section[];
    };
    documents: DocumentLink[];
};

const MUTED = 'color-mix(in srgb, var(--color-text) 62%, transparent)';

/**
 * One legal document, with the other three listed beside it.
 *
 * The article is capped at a reading measure rather than the shell's usual
 * clamp: the wide clamp exists so a dashboard does not strand its columns on a
 * 2560px screen, and prose has the opposite problem — a 1600px line is unread.
 * The shell around it still grows, so a header bar added later lines up.
 *
 * Clause numbers are rendered from the list position rather than written into
 * the copy, so inserting one in the middle renumbers the rest by itself.
 */
export default function Legal({ document, documents }: Props) {
    return (
        <PublicLayout>
            <Head title={`${document.title} - SDPC`} />

            <div
                className="page-shell"
                style={{
                    maxWidth: 'clamp(880px, 100vw - 320px, 1600px)',
                    margin: '0 auto',
                    paddingTop: 28,
                    paddingBottom: 72,
                }}
            >
                <Btn
                    asChild
                    variant="ghost"
                    style={{ fontSize: 12, marginLeft: -8 }}
                >
                    <Link href={home.url()}>← Back to SDPC</Link>
                </Btn>

                <article style={{ maxWidth: 760, marginTop: 26 }}>
                    <p
                        style={{
                            fontSize: 11,
                            letterSpacing: '0.14em',
                            textTransform: 'uppercase',
                            color: 'var(--color-accent)',
                            margin: 0,
                        }}
                    >
                        SDPC · Legal
                    </p>

                    <h1
                        style={{
                            fontFamily: 'var(--font-display)',
                            fontSize: 'clamp(28px, 5vw, 40px)',
                            lineHeight: 1.1,
                            letterSpacing: '-0.02em',
                            margin: '10px 0 0',
                            textWrap: 'balance',
                        }}
                    >
                        {document.title}
                    </h1>

                    <p
                        style={{
                            fontSize: 15,
                            lineHeight: 1.65,
                            color: MUTED,
                            margin: '14px 0 0',
                        }}
                    >
                        {document.intro}
                    </p>

                    <ol
                        style={{
                            listStyle: 'none',
                            margin: '34px 0 0',
                            padding: 0,
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 26,
                        }}
                    >
                        {document.sections.map((section, index) => (
                            <li
                                key={section.heading}
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '28px minmax(0, 1fr)',
                                    gap: 12,
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: 13,
                                        fontVariantNumeric: 'tabular-nums',
                                        color: 'var(--color-accent)',
                                        paddingTop: 2,
                                    }}
                                >
                                    {index + 1}.
                                </span>

                                <div>
                                    <h2
                                        style={{
                                            fontSize: 15,
                                            fontWeight: 600,
                                            margin: 0,
                                        }}
                                    >
                                        {section.heading}
                                    </h2>
                                    <p
                                        style={{
                                            fontSize: 14.5,
                                            lineHeight: 1.68,
                                            color: MUTED,
                                            margin: '6px 0 0',
                                        }}
                                    >
                                        {section.body}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ol>

                    <nav
                        aria-label="Other legal documents"
                        style={{
                            marginTop: 46,
                            paddingTop: 20,
                            borderTop:
                                '1px solid color-mix(in srgb, var(--color-text) 14%, transparent)',
                        }}
                    >
                        <p
                            style={{
                                fontSize: 11,
                                letterSpacing: '0.1em',
                                textTransform: 'uppercase',
                                color: MUTED,
                                margin: 0,
                            }}
                        >
                            The rest of the legal documents
                        </p>

                        <div
                            style={{
                                display: 'flex',
                                flexWrap: 'wrap',
                                gap: '10px 18px',
                                marginTop: 12,
                            }}
                        >
                            {documents
                                .filter((entry) => entry.slug !== document.slug)
                                .map((entry) => (
                                    <Link
                                        key={entry.slug}
                                        href={legal.url(entry.slug)}
                                        data-inline-link=""
                                        style={{ fontSize: 13.5 }}
                                    >
                                        {entry.title}
                                    </Link>
                                ))}
                        </div>
                    </nav>
                </article>
            </div>
        </PublicLayout>
    );
}
