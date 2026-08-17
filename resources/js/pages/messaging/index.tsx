import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { PaperPlaneRightIcon } from '@phosphor-icons/react';
import { useEffect, useRef } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Panel } from '@/components/sdpc/panel';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { send as sendMessage, show as showThread } from '@/routes/messages';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Thread = {
    id: number;
    title: string;
    subtitle: string;
    preview: string;
    at: string | null;
    isUnread: boolean;
    isActive: boolean;
};

type Props = {
    threads: Thread[];
    active: {
        id: number;
        title: string;
        project: string;
        messages: {
            id: number;
            body: string;
            author: string;
            isMine: boolean;
            at: string | null;
        }[];
    } | null;
};

/**
 * Messaging, shared by both modules.
 *
 * A thread exists per posting and student, so the list is a list of working
 * relationships rather than an address book. New messages arrive by polling —
 * there is no websocket server in this stack, and a five second poll is
 * honest about that rather than pretending to be live.
 */
export default function Messages({ threads, active }: Props) {
    const team = useCurrentTeam();
    const endRef = useRef<HTMLDivElement>(null);

    usePoll(5000, { only: ['threads', 'active'] });

    const form = useForm({ body: '' });

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [active?.messages.length, active?.id]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (active === null || form.data.body.trim() === '') {
            return;
        }

        form.post(
            sendMessage.url({
                current_team: team.slug,
                conversation: active.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => form.reset('body'),
            },
        );
    };

    return (
        <>
            <Head title="Messages" />

            <div
                style={{
                    maxWidth: 1320,
                    margin: '0 auto',
                    padding: '30px 32px 48px',
                }}
            >
                <div style={{ marginBottom: 18 }}>
                    <h3 style={{ margin: 0 }}>Messages</h3>
                    <div style={{ fontSize: 13, color: MUTED(68) }}>
                        One thread per project you are working on together
                    </div>
                </div>

                {threads.length === 0 ? (
                    <Panel padding="lg" gap="sm">
                        <span style={{ fontSize: 13 }}>No messages yet.</span>
                        <span style={{ fontSize: 12.5, color: MUTED(65) }}>
                            A thread opens once a student applies to one of your
                            postings, or once you apply to one. There is no way
                            to message someone you have no project with.
                        </span>
                    </Panel>
                ) : (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '300px minmax(0,1fr)',
                            gap: 16,
                            height: 'calc(100vh - 220px)',
                            minHeight: 420,
                        }}
                    >
                        <Panel
                            padding="none"
                            gap="none"
                            style={{ overflowY: 'auto' }}
                        >
                            {threads.map((thread) => (
                                <button
                                    key={thread.id}
                                    type="button"
                                    onClick={() =>
                                        router.get(
                                            showThread.url({
                                                current_team: team.slug,
                                                conversation: thread.id,
                                            }),
                                            {},
                                            { preserveState: false },
                                        )
                                    }
                                    style={{
                                        display: 'block',
                                        width: '100%',
                                        textAlign: 'left',
                                        border: 0,
                                        borderBottom:
                                            '1px solid var(--color-divider)',
                                        padding: '12px 14px',
                                        font: 'inherit',
                                        cursor: 'pointer',
                                        color: 'var(--color-text)',
                                        background: thread.isActive
                                            ? 'color-mix(in srgb, var(--color-accent) 12%, transparent)'
                                            : 'transparent',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'baseline',
                                            gap: 8,
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: 13.5,
                                                marginRight: 'auto',
                                                fontWeight: thread.isUnread
                                                    ? 600
                                                    : 400,
                                            }}
                                        >
                                            {thread.title}
                                        </span>
                                        {thread.isUnread && (
                                            <span
                                                aria-label="Unread"
                                                style={{
                                                    width: 7,
                                                    height: 7,
                                                    borderRadius: '50%',
                                                    background:
                                                        'var(--color-accent)',
                                                }}
                                            />
                                        )}
                                        {thread.at && (
                                            <span
                                                style={{
                                                    fontSize: 10.5,
                                                    color: MUTED(60),
                                                }}
                                            >
                                                {thread.at}
                                            </span>
                                        )}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11,
                                            color: MUTED(65),
                                        }}
                                    >
                                        {thread.subtitle}
                                    </div>
                                    {thread.preview && (
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: MUTED(55),
                                                marginTop: 2,
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {thread.preview}
                                        </div>
                                    )}
                                </button>
                            ))}
                        </Panel>

                        {active !== null && (
                            <Panel
                                padding="none"
                                gap="none"
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    minHeight: 0,
                                }}
                            >
                                <div
                                    style={{
                                        padding: '13px 18px',
                                        borderBottom:
                                            '1px solid var(--color-divider)',
                                    }}
                                >
                                    <div style={{ fontSize: 14 }}>
                                        {active.title}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: MUTED(65),
                                        }}
                                    >
                                        {active.project}
                                    </div>
                                </div>

                                <div
                                    style={{
                                        flex: 1,
                                        overflowY: 'auto',
                                        padding: 18,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 10,
                                    }}
                                >
                                    {active.messages.length === 0 && (
                                        <span
                                            style={{
                                                fontSize: 12.5,
                                                color: MUTED(55),
                                                margin: 'auto',
                                            }}
                                        >
                                            No messages yet. Say hello.
                                        </span>
                                    )}

                                    {active.messages.map((message) => (
                                        <div
                                            key={message.id}
                                            style={{
                                                alignSelf: message.isMine
                                                    ? 'flex-end'
                                                    : 'flex-start',
                                                maxWidth: '68%',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    padding: '9px 12px',
                                                    borderRadius:
                                                        'var(--radius-md)',
                                                    fontSize: 13,
                                                    lineHeight: 1.5,
                                                    whiteSpace: 'pre-wrap',
                                                    wordBreak: 'break-word',
                                                    background: message.isMine
                                                        ? 'color-mix(in srgb, var(--color-accent) 16%, transparent)'
                                                        : 'color-mix(in srgb, var(--color-text) 6%, transparent)',
                                                }}
                                            >
                                                {message.body}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 10.5,
                                                    color: MUTED(55),
                                                    marginTop: 3,
                                                    textAlign: message.isMine
                                                        ? 'right'
                                                        : 'left',
                                                }}
                                            >
                                                {message.isMine
                                                    ? 'You'
                                                    : message.author}
                                                {message.at
                                                    ? ` · ${message.at}`
                                                    : ''}
                                            </div>
                                        </div>
                                    ))}

                                    <div ref={endRef} />
                                </div>

                                <form
                                    onSubmit={submit}
                                    style={{
                                        display: 'flex',
                                        gap: 8,
                                        padding: 14,
                                        borderTop:
                                            '1px solid var(--color-divider)',
                                    }}
                                >
                                    <textarea
                                        className="input"
                                        rows={1}
                                        maxLength={4000}
                                        placeholder="Write a message"
                                        value={form.data.body}
                                        onChange={(e) =>
                                            form.setData('body', e.target.value)
                                        }
                                        onKeyDown={(e) => {
                                            if (
                                                e.key === 'Enter' &&
                                                !e.shiftKey
                                            ) {
                                                submit(e);
                                            }
                                        }}
                                        style={{
                                            flex: 1,
                                            resize: 'none',
                                            minHeight: 38,
                                        }}
                                    />
                                    <Btn
                                        variant="primary"
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            form.data.body.trim() === ''
                                        }
                                    >
                                        <PaperPlaneRightIcon />
                                        Send
                                    </Btn>
                                </form>
                            </Panel>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
