import { useEcho } from '@laravel/echo-react';
import { Head, router, useForm, usePoll } from '@inertiajs/react';
import {
    ImageIcon,
    PaperPlaneRightIcon,
    SmileyIcon,
} from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Panel } from '@/components/sdpc/panel';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    edit as editMessage,
    react as reactToMessage,
    remove as removeMessageRoute,
    send as sendMessage,
    show as showThread,
} from '@/routes/messages';

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
            body: string | null;
            author: string;
            isMine: boolean;
            at: string | null;
            isEdited: boolean;
            isRemoved: boolean;
            editableUntil: number | null;
            imageUrl: string | null;
            reactions: { emoji: string; count: number; reacted: boolean }[];
        }[];
        reactionChoices: string[];
    } | null;
};

/** A short row of emoji for the composer, not a full keyboard. */
const QUICK_EMOJI = ['😀', '😂', '🙏', '👍', '🎉', '🔥', '❤️', '😢', '👀', '✅'];

/**
 * Whether a message is nothing but a few emoji.
 *
 * Those get drawn large and without a bubble, the way every chat app does it —
 * a lone 🔥 sitting in a full-width panel reads as a mistake. Capped at a
 * handful so a wall of emoji stays a normal message rather than filling the
 * thread.
 */
function isEmojiOnly(message: { body: string | null; imageUrl: string | null }) {
    if (message.imageUrl !== null || message.body === null) {
        return false;
    }

    const text = message.body.trim();

    if (text === '') {
        return false;
    }

    // Extended_Pictographic covers emoji proper; the rest are the joiners,
    // skin-tone modifiers and variation selectors that compose them.
    const emojiOnly = /^(\p{Extended_Pictographic}|\p{Emoji_Component}|‍|️|\s)+$/u;

    return (
        emojiOnly.test(text) &&
        [...new Intl.Segmenter().segment(text)].length <= 3
    );
}

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
    const imageRef = useRef<HTMLInputElement>(null);

    /*
     * The thread arrives over a socket, so a message shows up as it is sent
     * rather than on the next tick of a timer.
     *
     * The poll stays as a backstop at a much longer interval: Reverb is a
     * separate process someone has to start, and a chat that silently stops
     * updating when it is not running is worse than one that is briefly slow.
     */
    usePoll(30000, { only: ['threads', 'active'] });

    useEcho(
        `conversations.${active?.id ?? 0}`,
        '.message.sent',
        () => {
            router.reload({ only: ['threads', 'active'] });
        },
        [active?.id],
    );

    const form = useForm({ body: '', image: null as File | null });

    /** The message being edited, and the text as it stands mid-edit. */
    const [editing, setEditing] = useState<number | null>(null);
    const [draft, setDraft] = useState('');
    const [emojiOpen, setEmojiOpen] = useState(false);

    /*
     * A clock, so the Edit link retires itself when the window closes rather
     * than sitting there until something else causes a re-render.
     */
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const tick = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(tick);
    }, []);

    /*
     * Which message the cursor has rested on long enough to show its reaction
     * picker. Held for a beat so the row of emoji does not flash up at every
     * mouse movement across the thread.
     */
    const [hovered, setHovered] = useState<number | null>(null);
    const hoverTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const startHover = (messageId: number) => {
        if (hoverTimer.current !== null) {
            clearTimeout(hoverTimer.current);
        }

        hoverTimer.current = setTimeout(() => setHovered(messageId), 2000);
    };

    const endHover = () => {
        if (hoverTimer.current !== null) {
            clearTimeout(hoverTimer.current);
            hoverTimer.current = null;
        }

        setHovered(null);
    };

    // Leaving the page mid-hover must not fire the timer into a gone component.
    useEffect(
        () => () => {
            if (hoverTimer.current !== null) {
                clearTimeout(hoverTimer.current);
            }
        },
        [],
    );

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [active?.messages.length, active?.id]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // A picture on its own is a message, so an empty box is only empty
        // when nothing is attached either.
        if (
            active === null ||
            (form.data.body.trim() === '' && form.data.image === null)
        ) {
            return;
        }

        form.post(
            sendMessage.url({
                current_team: team.slug,
                conversation: active.id,
            }),
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    form.reset('body', 'image');
                    setEmojiOpen(false);
                },
            },
        );
    };

    const saveEdit = (messageId: number) => {
        if (active === null || draft.trim() === '') {
            return;
        }

        router.patch(
            editMessage.url({
                current_team: team.slug,
                conversation: active.id,
                message: messageId,
            }),
            { body: draft },
            { preserveScroll: true, onSuccess: () => setEditing(null) },
        );
    };

    const removeMessage = (messageId: number) => {
        if (active === null) {
            return;
        }

        router.delete(
            removeMessageRoute.url({
                current_team: team.slug,
                conversation: active.id,
                message: messageId,
            }),
            { preserveScroll: true },
        );
    };

    const react = (messageId: number, emoji: string) => {
        if (active === null) {
            return;
        }

        router.post(
            reactToMessage.url({
                current_team: team.slug,
                conversation: active.id,
                message: messageId,
            }),
            { emoji },
            { preserveScroll: true },
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
                                            onMouseEnter={() =>
                                                startHover(message.id)
                                            }
                                            onMouseLeave={endHover}
                                            style={{
                                                alignSelf: message.isMine
                                                    ? 'flex-end'
                                                    : 'flex-start',
                                                maxWidth: '68%',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    /*
                                                     * Hug the content. Without
                                                     * this the bubble is a
                                                     * block and stretches to
                                                     * whatever the widest row
                                                     * below it is — the meta
                                                     * line "You · 11s ago Edit
                                                     * Remove" — so a one
                                                     * character message drew a
                                                     * bubble wide enough for a
                                                     * sentence.
                                                     */
                                                    width: 'fit-content',
                                                    maxWidth: '100%',
                                                    marginLeft: message.isMine
                                                        ? 'auto'
                                                        : undefined,
                                                    padding: isEmojiOnly(
                                                        message,
                                                    )
                                                        ? 0
                                                        : '9px 12px',
                                                    borderRadius:
                                                        'var(--radius-md)',
                                                    // A message that is only
                                                    // emoji is read, not
                                                    // parsed, so it gets the
                                                    // room to be seen.
                                                    fontSize: isEmojiOnly(
                                                        message,
                                                    )
                                                        ? 34
                                                        : 13,
                                                    lineHeight: isEmojiOnly(
                                                        message,
                                                    )
                                                        ? 1.15
                                                        : 1.5,
                                                    whiteSpace: 'pre-wrap',
                                                    wordBreak: 'break-word',
                                                    fontStyle: message.isRemoved
                                                        ? 'italic'
                                                        : undefined,
                                                    color: message.isRemoved
                                                        ? MUTED(50)
                                                        : undefined,
                                                    background:
                                                        message.isRemoved ||
                                                        isEmojiOnly(message)
                                                            ? 'transparent'
                                                            : message.isMine
                                                              ? 'color-mix(in srgb, var(--color-accent) 16%, transparent)'
                                                              : 'color-mix(in srgb, var(--color-text) 6%, transparent)',
                                                    border: message.isRemoved
                                                        ? `1px dashed ${MUTED(20)}`
                                                        : undefined,
                                                }}
                                            >
                                                {message.isRemoved ? (
                                                    'Message removed'
                                                ) : editing === message.id ? (
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            flexDirection:
                                                                'column',
                                                            gap: 6,
                                                        }}
                                                    >
                                                        <textarea
                                                            value={draft}
                                                            rows={2}
                                                            autoFocus
                                                            onChange={(e) =>
                                                                setDraft(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            style={{
                                                                fontSize: 13,
                                                                padding: 6,
                                                                borderRadius: 6,
                                                                border: `1px solid ${MUTED(20)}`,
                                                                background:
                                                                    'var(--color-surface, #fff)',
                                                            }}
                                                        />
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                gap: 6,
                                                            }}
                                                        >
                                                            <Btn
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    padding:
                                                                        '3px 9px',
                                                                }}
                                                                onClick={() =>
                                                                    saveEdit(
                                                                        message.id,
                                                                    )
                                                                }
                                                            >
                                                                Save
                                                            </Btn>
                                                            <Btn
                                                                variant="ghost"
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    padding:
                                                                        '3px 9px',
                                                                }}
                                                                onClick={() =>
                                                                    setEditing(
                                                                        null,
                                                                    )
                                                                }
                                                            >
                                                                Cancel
                                                            </Btn>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <>
                                                        {message.imageUrl && (
                                                            <img
                                                                src={
                                                                    message.imageUrl
                                                                }
                                                                alt=""
                                                                style={{
                                                                    maxWidth:
                                                                        '100%',
                                                                    borderRadius: 8,
                                                                    marginBottom:
                                                                        message.body
                                                                            ? 6
                                                                            : 0,
                                                                    display:
                                                                        'block',
                                                                }}
                                                            />
                                                        )}
                                                        {message.body}
                                                    </>
                                                )}
                                            </div>

                                            {/*
                                              * Reactions already left, plus the
                                              * picker while hovered. Rendered
                                              * only when there is something to
                                              * show, so an untouched message
                                              * carries no empty strip.
                                              */}
                                            {!message.isRemoved &&
                                                (message.reactions.length > 0 ||
                                                    hovered === message.id) && (
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        flexWrap: 'wrap',
                                                        gap: 4,
                                                        marginTop: 4,
                                                        justifyContent:
                                                            message.isMine
                                                                ? 'flex-end'
                                                                : 'flex-start',
                                                    }}
                                                >
                                                    {message.reactions.map(
                                                        (reaction) => (
                                                            <button
                                                                key={
                                                                    reaction.emoji
                                                                }
                                                                type="button"
                                                                onClick={() =>
                                                                    react(
                                                                        message.id,
                                                                        reaction.emoji,
                                                                    )
                                                                }
                                                                title="Toggle this reaction"
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    padding:
                                                                        '1px 7px',
                                                                    borderRadius: 999,
                                                                    border: `1px solid ${reaction.reacted ? 'var(--color-primary, #4a7c4e)' : MUTED(18)}`,
                                                                    background:
                                                                        reaction.reacted
                                                                            ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)'
                                                                            : 'transparent',
                                                                }}
                                                            >
                                                                {reaction.emoji}{' '}
                                                                {reaction.count}
                                                            </button>
                                                        ),
                                                    )}

                                                    {/*
                                                      * Appears once the cursor
                                                      * has rested on the
                                                      * message for two seconds,
                                                      * so it does not flash up
                                                      * at every pass of the
                                                      * mouse across the thread.
                                                      */}
                                                    {hovered === message.id && (
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                gap: 2,
                                                                padding: 3,
                                                                borderRadius: 999,
                                                                border: `1px solid ${MUTED(15)}`,
                                                                background:
                                                                    'var(--color-surface, #fff)',
                                                            }}
                                                        >
                                                            {active.reactionChoices.map(
                                                                (emoji) => (
                                                                    <button
                                                                        key={
                                                                            emoji
                                                                        }
                                                                        type="button"
                                                                        title="React"
                                                                        onClick={() =>
                                                                            react(
                                                                                message.id,
                                                                                emoji,
                                                                            )
                                                                        }
                                                                        style={{
                                                                            fontSize: 14,
                                                                            padding:
                                                                                '1px 3px',
                                                                            lineHeight: 1,
                                                                        }}
                                                                    >
                                                                        {emoji}
                                                                    </button>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            <div
                                                style={{
                                                    fontSize: 10.5,
                                                    color: MUTED(55),
                                                    marginTop: 3,
                                                    display: 'flex',
                                                    gap: 6,
                                                    justifyContent:
                                                        message.isMine
                                                            ? 'flex-end'
                                                            : 'flex-start',
                                                }}
                                            >
                                                <span>
                                                    {message.isMine
                                                        ? 'You'
                                                        : message.author}
                                                    {message.at
                                                        ? ` · ${message.at}`
                                                        : ''}
                                                    {message.isEdited
                                                        ? ' · edited'
                                                        : ''}
                                                </span>

                                                {/* Only the sender's own, and
                                                    not once it is removed. */}
                                                {message.isMine &&
                                                    !message.isRemoved &&
                                                    editing !== message.id && (
                                                        <>
                                                            {/*
                                                              * Editing closes
                                                              * 30 seconds after
                                                              * sending. The
                                                              * clock ticking
                                                              * above is what
                                                              * makes this
                                                              * disappear on its
                                                              * own; the server
                                                              * refuses it
                                                              * either way.
                                                              */}
                                                            {message.editableUntil !==
                                                                null &&
                                                                now <
                                                                    message.editableUntil && (
                                                                    <button
                                                                        type="button"
                                                                        title={`Editable for ${Math.max(0, Math.ceil((message.editableUntil - now) / 1000))}s more`}
                                                                        onClick={() => {
                                                                            setEditing(
                                                                                message.id,
                                                                            );
                                                                            setDraft(
                                                                                message.body ??
                                                                                    '',
                                                                            );
                                                                        }}
                                                                        style={{
                                                                            color: MUTED(
                                                                                60,
                                                                            ),
                                                                            textDecoration:
                                                                                'underline',
                                                                        }}
                                                                    >
                                                                        Edit
                                                                    </button>
                                                                )}
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    removeMessage(
                                                                        message.id,
                                                                    )
                                                                }
                                                                style={{
                                                                    color: MUTED(
                                                                        60,
                                                                    ),
                                                                    textDecoration:
                                                                        'underline',
                                                                }}
                                                            >
                                                                Remove
                                                            </button>
                                                        </>
                                                    )}
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
                                    {/* Emoji are characters in the body, so
                                        this needs nothing from the server. */}
                                    <Btn
                                        variant="ghost"
                                        type="button"
                                        title="Emoji"
                                        onClick={() =>
                                            setEmojiOpen((open) => !open)
                                        }
                                    >
                                        <SmileyIcon />
                                    </Btn>

                                    <Btn
                                        variant="ghost"
                                        type="button"
                                        title="Attach a picture"
                                        onClick={() =>
                                            imageRef.current?.click()
                                        }
                                    >
                                        <ImageIcon />
                                    </Btn>
                                    <input
                                        ref={imageRef}
                                        type="file"
                                        accept="image/*"
                                        hidden
                                        onChange={(e) =>
                                            form.setData(
                                                'image',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />

                                    <Btn
                                        variant="primary"
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            (form.data.body.trim() === '' &&
                                                form.data.image === null)
                                        }
                                    >
                                        <PaperPlaneRightIcon />
                                        Send
                                    </Btn>
                                </form>

                                {/* Sits under the composer so neither the
                                    picker nor the attachment note covers the
                                    last message in the thread. */}
                                {(emojiOpen || form.data.image !== null) && (
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            flexWrap: 'wrap',
                                            gap: 6,
                                            padding: '0 14px 12px',
                                        }}
                                    >
                                        {emojiOpen &&
                                            QUICK_EMOJI.map((emoji) => (
                                                <button
                                                    key={emoji}
                                                    type="button"
                                                    onClick={() =>
                                                        form.setData(
                                                            'body',
                                                            form.data.body +
                                                                emoji,
                                                        )
                                                    }
                                                    style={{ fontSize: 18 }}
                                                >
                                                    {emoji}
                                                </button>
                                            ))}

                                        {form.data.image !== null && (
                                            <span
                                                style={{
                                                    fontSize: 11.5,
                                                    color: MUTED(60),
                                                    display: 'flex',
                                                    gap: 6,
                                                    alignItems: 'center',
                                                }}
                                            >
                                                {form.data.image.name}
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        form.setData(
                                                            'image',
                                                            null,
                                                        );

                                                        if (imageRef.current) {
                                                            imageRef.current.value =
                                                                '';
                                                        }
                                                    }}
                                                    style={{
                                                        textDecoration:
                                                            'underline',
                                                    }}
                                                >
                                                    remove
                                                </button>
                                            </span>
                                        )}
                                    </div>
                                )}
                            </Panel>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
