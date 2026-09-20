import {
    ArrowBendUpLeftIcon,
    DotsThreeVerticalIcon,
    SmileyIcon,
} from '@phosphor-icons/react';
import { useEffect, useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import UserAvatar from '@/components/sdpc/user-avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

export type Reaction = { emoji: string; count: number; reacted: boolean };

export type ChatMessage = {
    id: number;
    body: string | null;
    author: string;
    /** Resolved by User::avatarUrl(); null when the account has no picture. */
    authorAvatarUrl: string | null;
    isMine: boolean;
    at: string | null;
    isEdited: boolean;
    isRemoved: boolean;
    editableUntil: number | null;
    imageUrl: string | null;
    reactions: Reaction[];
    /** The line this message answers, if it answers one. */
    replyTo: { id: number; author: string; excerpt: string } | null;
};

/**
 * Whether a message is nothing but a few emoji.
 *
 * Those get drawn large and without a bubble, the way every chat app does it —
 * a lone 🔥 sitting in a full-width panel reads as a mistake. Capped at a
 * handful so a wall of emoji stays a normal message rather than filling the
 * thread.
 */
export function isEmojiOnly(message: {
    body: string | null;
    imageUrl: string | null;
}) {
    if (message.imageUrl !== null || message.body === null) {
        return false;
    }

    const text = message.body.trim();

    if (text === '') {
        return false;
    }

    // Extended_Pictographic covers emoji proper; the rest are the joiners,
    // skin-tone modifiers and variation selectors that compose them.
    const emojiOnly =
        /^(\p{Extended_Pictographic}|\p{Emoji_Component}|‍|️|\s)+$/u;

    return (
        emojiOnly.test(text) &&
        [...new Intl.Segmenter().segment(text)].length <= 3
    );
}

/**
 * One message in the thread, with the actions that appear beside it.
 *
 * Laid out the way every chat app lays this out: the other side's picture, the
 * bubble, and — only once the pointer rests on the row — a react / reply /
 * more cluster. The reveal, the lift under the pointer and the pop a new
 * reaction makes are all in nocturne.css under .msg-row, because this screen
 * sets its geometry inline and an inline style beats every selector.
 *
 * Removal is two different things and the menu says so. "Remove for everyone"
 * takes the message back from the thread and is the sender's alone; "Remove
 * for you" hides one line from this account and changes nothing for anybody
 * else, so it is offered on anyone's message.
 */
export default function MessageRow({
    message,
    reactionChoices,
    now,
    isEditing,
    draft,
    onDraftChange,
    onStartEdit,
    onCancelEdit,
    onSaveEdit,
    onReact,
    onReply,
    onRemoveForEveryone,
    onRemoveForMe,
}: {
    message: ChatMessage;
    reactionChoices: string[];
    /** Ticking clock, so the edit window closes on its own. */
    now: number;
    isEditing: boolean;
    draft: string;
    onDraftChange: (value: string) => void;
    onStartEdit: () => void;
    onCancelEdit: () => void;
    onSaveEdit: () => void;
    onReact: (emoji: string) => void;
    onReply: () => void;
    onRemoveForEveryone: () => void;
    onRemoveForMe: () => void;
}) {
    const [menuOpen, setMenuOpen] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [confirming, setConfirming] = useState(false);

    /*
     * Which reaction has just been left, so .msg-reaction can pop it once. It
     * clears itself, otherwise the animation would never fire twice on the
     * same emoji.
     */
    const [justAdded, setJustAdded] = useState<string | null>(null);

    useEffect(() => {
        if (justAdded === null) {
            return;
        }

        const timer = setTimeout(() => setJustAdded(null), 400);

        return () => clearTimeout(timer);
    }, [justAdded]);

    const emojiOnly = isEmojiOnly(message);

    /*
     * Editing closes 30 seconds after sending. The clock above is what makes
     * this disappear on its own; the server refuses it either way.
     */
    const canEdit =
        message.isMine &&
        !message.isRemoved &&
        !isEditing &&
        message.editableUntil !== null &&
        now < message.editableUntil;

    const canRemoveForEveryone = message.isMine && !message.isRemoved;

    const react = (emoji: string) => {
        setJustAdded(emoji);
        setPickerOpen(false);
        onReact(emoji);
    };

    return (
        <div
            className="msg-row"
            style={{
                alignSelf: message.isMine ? 'flex-end' : 'flex-start',
                maxWidth: '78%',
                flexDirection: message.isMine ? 'row-reverse' : 'row',
            }}
        >
            {/* Your own picture is not drawn beside your own words. */}
            {!message.isMine && (
                <UserAvatar
                    name={message.author}
                    avatarUrl={message.authorAvatarUrl}
                    size={28}
                />
            )}

            <div style={{ minWidth: 0 }}>
                {/* What this message answers, above what it says. */}
                {message.replyTo !== null && (
                    <div
                        className="msg-quote"
                        style={{
                            fontSize: 11,
                            color: MUTED(60),
                            marginBottom: 3,
                            marginLeft: message.isMine ? 'auto' : undefined,
                            width: 'fit-content',
                            maxWidth: '100%',
                        }}
                    >
                        <span style={{ fontWeight: 600 }}>
                            {message.replyTo.author}
                        </span>{' '}
                        <span
                            style={{
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                                display: 'inline-block',
                                maxWidth: 220,
                                verticalAlign: 'bottom',
                            }}
                        >
                            {message.replyTo.excerpt}
                        </span>
                    </div>
                )}

                <div
                    style={{
                        /*
                         * Hug the content. Without this the bubble is a block
                         * and stretches to whatever the widest row below it
                         * is — the meta line — so a one character message drew
                         * a bubble wide enough for a sentence.
                         */
                        width: 'fit-content',
                        maxWidth: '100%',
                        marginLeft: message.isMine ? 'auto' : undefined,
                        padding: emojiOnly ? 0 : '9px 12px',
                        borderRadius: 'var(--radius-md)',
                        fontSize: emojiOnly ? 34 : 13,
                        lineHeight: emojiOnly ? 1.15 : 1.5,
                        whiteSpace: 'pre-wrap',
                        wordBreak: 'break-word',
                        fontStyle: message.isRemoved ? 'italic' : undefined,
                        color: message.isRemoved ? MUTED(50) : undefined,
                        background:
                            message.isRemoved || emojiOnly
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
                    ) : isEditing ? (
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 6,
                            }}
                        >
                            <textarea
                                value={draft}
                                rows={2}
                                autoFocus
                                onChange={(event) =>
                                    onDraftChange(event.target.value)
                                }
                                style={{
                                    fontSize: 13,
                                    padding: 6,
                                    borderRadius: 6,
                                    border: `1px solid ${MUTED(20)}`,
                                    background: 'var(--color-surface, #fff)',
                                }}
                            />
                            <div style={{ display: 'flex', gap: 6 }}>
                                <Btn
                                    style={{
                                        fontSize: 11.5,
                                        padding: '3px 9px',
                                    }}
                                    onClick={onSaveEdit}
                                >
                                    Save
                                </Btn>
                                <Btn
                                    variant="ghost"
                                    style={{
                                        fontSize: 11.5,
                                        padding: '3px 9px',
                                    }}
                                    onClick={onCancelEdit}
                                >
                                    Cancel
                                </Btn>
                            </div>
                        </div>
                    ) : (
                        <>
                            {message.imageUrl && (
                                <img
                                    src={message.imageUrl}
                                    alt=""
                                    style={{
                                        maxWidth: '100%',
                                        borderRadius: 8,
                                        marginBottom: message.body ? 6 : 0,
                                        display: 'block',
                                    }}
                                />
                            )}
                            {message.body}
                        </>
                    )}
                </div>

                {/* Reactions already left. The picker is in the cluster now. */}
                {!message.isRemoved && message.reactions.length > 0 && (
                    <div
                        style={{
                            display: 'flex',
                            flexWrap: 'wrap',
                            gap: 4,
                            marginTop: 4,
                            justifyContent: message.isMine
                                ? 'flex-end'
                                : 'flex-start',
                        }}
                    >
                        {message.reactions.map((reaction) => (
                            <button
                                key={reaction.emoji}
                                type="button"
                                className="msg-reaction"
                                data-just-added={
                                    justAdded === reaction.emoji
                                        ? 'true'
                                        : undefined
                                }
                                onClick={() => react(reaction.emoji)}
                                title="Toggle this reaction"
                                style={{
                                    fontSize: 11.5,
                                    padding: '1px 7px',
                                    borderRadius: 999,
                                    border: `1px solid ${reaction.reacted ? 'var(--color-accent)' : MUTED(18)}`,
                                    background: reaction.reacted
                                        ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)'
                                        : 'transparent',
                                }}
                            >
                                {reaction.emoji} {reaction.count}
                            </button>
                        ))}
                    </div>
                )}

                <div
                    style={{
                        fontSize: 10.5,
                        color: MUTED(55),
                        marginTop: 3,
                        display: 'flex',
                        gap: 6,
                        alignItems: 'center',
                        justifyContent: message.isMine
                            ? 'flex-end'
                            : 'flex-start',
                    }}
                >
                    <span>
                        {message.isMine ? 'You' : message.author}
                        {message.at ? ` · ${message.at}` : ''}
                        {message.isEdited ? ' · edited' : ''}
                    </span>

                    {/* Asked first: a removed message cannot be brought back. */}
                    {confirming && (
                        <>
                            <span>Remove for everyone?</span>
                            <button
                                type="button"
                                onClick={() => {
                                    setConfirming(false);
                                    onRemoveForEveryone();
                                }}
                                style={{
                                    color: MUTED(60),
                                    textDecoration: 'underline',
                                }}
                            >
                                Yes, remove
                            </button>
                            <button
                                type="button"
                                onClick={() => setConfirming(false)}
                                style={{
                                    color: MUTED(60),
                                    textDecoration: 'underline',
                                }}
                            >
                                Cancel
                            </button>
                        </>
                    )}
                </div>
            </div>

            {/*
             * React / reply / more. Hidden until the row is hovered or
             * focused, and held open while its own menu is — see .msg-actions.
             */}
            {!message.isRemoved && !isEditing && (
                <div
                    className="msg-actions"
                    data-open={menuOpen || pickerOpen ? 'true' : undefined}
                >
                    <DropdownMenu
                        open={pickerOpen}
                        onOpenChange={setPickerOpen}
                    >
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="msg-action"
                                title="React"
                                aria-label="React to this message"
                            >
                                <SmileyIcon size={16} />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align={message.isMine ? 'end' : 'start'}
                            className="nocturne flex min-w-0 gap-1 p-1"
                        >
                            {reactionChoices.map((emoji) => (
                                <button
                                    key={emoji}
                                    type="button"
                                    className="msg-emoji"
                                    title={`React ${emoji}`}
                                    aria-label={`React ${emoji}`}
                                    onClick={() => react(emoji)}
                                >
                                    {emoji}
                                </button>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <button
                        type="button"
                        className="msg-action"
                        title="Reply"
                        aria-label="Reply to this message"
                        onClick={onReply}
                    >
                        <ArrowBendUpLeftIcon size={16} />
                    </button>

                    <DropdownMenu open={menuOpen} onOpenChange={setMenuOpen}>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="msg-action"
                                title="More"
                                aria-label="More actions for this message"
                            >
                                <DotsThreeVerticalIcon size={16} />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align={message.isMine ? 'end' : 'start'}
                            className="nocturne"
                        >
                            {canEdit && (
                                <DropdownMenuItem onSelect={onStartEdit}>
                                    Edit
                                </DropdownMenuItem>
                            )}
                            {canRemoveForEveryone && (
                                <DropdownMenuItem
                                    onSelect={() => setConfirming(true)}
                                >
                                    Remove for everyone
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem onSelect={onRemoveForMe}>
                                Remove for you
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            )}
        </div>
    );
}
